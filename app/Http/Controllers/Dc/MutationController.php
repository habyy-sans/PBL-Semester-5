<?php

namespace App\Http\Controllers\Dc;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MutationController extends Controller
{
    private const FILTER_SESSION = 'dc_mutation_filters';

    /**
     * Menampilkan riwayat mutasi DC.
     */
    public function index(Request $request): View
    {
        $dcId = $request->user()->dc_id;

        abort_if(
            $dcId === null,
            403,
            'Akun DC belum memiliki penugasan.'
        );

        // Filter hanya dibaca dari session, bukan query string.
        $filters = session(self::FILTER_SESSION, []);

        $query = StockMovement::query()
            ->with('bahan')
            ->where('dc_id', $dcId)
            ->whereNull('cabang_id')
            ->whereIn('jenis_mutasi', [
                'penerimaan_dc',
                'pengiriman_dc',
                'pembatalan_pengiriman_dc',
            ]);

        if (!empty($filters['jenis_mutasi'])) {
            $query->where(
                'jenis_mutasi',
                $filters['jenis_mutasi']
            );
        }

        if (!empty($filters['tanggal_mulai'])) {
            $query->whereDate(
                'tanggal',
                '>=',
                $filters['tanggal_mulai']
            );
        }

        if (!empty($filters['tanggal_akhir'])) {
            $query->whereDate(
                'tanggal',
                '<=',
                $filters['tanggal_akhir']
            );
        }

        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];

            $query->whereHas('bahan', function ($q) use ($keyword) {
                $q->where(
                    'nama_bahan',
                    'ilike',
                    "%{$keyword}%"
                );
            });
        }

        // Statistik dihitung dari data yang sesuai filter.
        $summary = [
            'total' => (clone $query)->count(),

            'penerimaan' => (clone $query)
                ->where('jenis_mutasi', 'penerimaan_dc')
                ->count(),

            'pengiriman' => (clone $query)
                ->where('jenis_mutasi', 'pengiriman_dc')
                ->count(),

            'pembatalan' => (clone $query)
                ->where('jenis_mutasi', 'pembatalan_pengiriman_dc')
                ->count(),
        ];

        $mutations = $query
            ->orderByDesc('tanggal')
            ->orderByDesc('movement_id')
            ->paginate(15);

        return view('dc.mutasi.index', compact(
            'mutations',
            'filters',
            'summary'
        ));
    }

    /**
     * Memproses filter melalui POST.
     * Nilai filter disimpan dalam session agar URL tetap bersih.
     */
    public function filter(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'keyword' => [
                'nullable',
                'string',
                'max:100',
            ],
            'jenis_mutasi' => [
                'nullable',
                'in:penerimaan_dc,pengiriman_dc,pembatalan_pengiriman_dc',
            ],
            'tanggal_mulai' => [
                'nullable',
                'date',
            ],
            'tanggal_akhir' => [
                'nullable',
                'date',
                'after_or_equal:tanggal_mulai',
            ],
        ]);

        $filters = array_filter(
            $data,
            fn ($value) => $value !== null && $value !== ''
        );

        session()->put(self::FILTER_SESSION, $filters);

        return redirect()->route('dc.mutation');
    }

    /**
     * Menghapus filter dari session.
     */
    public function resetFilter(): RedirectResponse
    {
        session()->forget(self::FILTER_SESSION);

        return redirect()->route('dc.mutation');
    }
}
