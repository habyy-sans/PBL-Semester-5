<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\DraftInstruksi;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use App\Models\TransaksiPenjualan;
use App\Services\CatalogService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly ReportService $report,
    ) {}

    /**
     * Dashboard Manager — window.KOPSYNC diisi dari database dengan bentuk
     * yang sama persis seperti data demo lama supaya dashboard.js tidak berubah.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $cabangId = (int) $user->cabang_id;
        $cabang = Cabang::query()->find($cabangId);

        $namaCabang = $cabang->nama_cabang ?? 'Cabang';
        $parts = explode(' · ', $namaCabang);

        return view('manager.dashboard.index', [
            'branchName' => count($parts) > 1 ? $parts[0] : $namaCabang,
            'storeName' => $namaCabang,
            'payload' => [
                'items' => $this->catalog->items($cabangId),
                'menus' => $this->catalog->menus(),
                'transactions' => $this->transactions($cabangId),
                'mutations' => $this->mutations($cabangId),
                'shipments' => $this->shipments($cabangId),
                'summary' => $this->report->branchSummary($cabangId),
                'draftNo' => $this->latestDraftNo($cabangId),
            ],
        ]);
    }

    /**
     * Halaman Alert — data restock (stok di bawah minimum) + mutasi waste
     * dari database, plus no. draft instruksi terbaru.
     */
    public function alert(Request $request): View
    {
        $user = $request->user();
        $cabangId = (int) $user->cabang_id;

        return view('manager.alert.index', [
            'items' => $this->catalog->items($cabangId),
            'mutations' => $this->mutations($cabangId),
            'draftNo' => $this->latestDraftNo($cabangId),
        ]);
    }

    private function latestDraftNo(int $cabangId): ?string
    {
        $draftId = DraftInstruksi::query()
            ->where('cabang_id', $cabangId)
            ->max('draft_id');

        return $draftId !== null ? sprintf('#RS-%04d', $draftId) : null;
    }

    /**
     * Riwayat transaksi (berhasil/batal) untuk JS dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    private function transactions(int $cabangId): array
    {
        return TransaksiPenjualan::query()
            ->where('cabang_id', $cabangId)
            ->with(['detail.varian', 'kasir', 'pembayaran'])
            ->orderByDesc('tanggal')
            ->limit(300)
            ->get()
            ->map(fn ($trx) => [
                'no' => $trx->no_transaksi ?? sprintf('#TRX-%04d', $trx->transaksi_id),
                'date' => $trx->tanggal->format('Y-m-d'),
                'time' => $trx->tanggal->format('H:i'),
                'cashier' => $trx->kasir?->nama_lengkap ?? '-',
                'method' => $trx->pembayaran?->metode ?? 'tunai',
                'cash' => $trx->pembayaran?->jumlah_diterima !== null
                    ? (float) $trx->pembayaran->jumlah_diterima
                    : null,
                'status' => $trx->status === 'berhasil' ? 'selesai' : 'batal',
                'reason' => $trx->alasan_batal,
                'items' => $trx->detail
                    ->filter(fn ($line) => $line->varian !== null)
                    ->map(fn ($line) => [
                        'menu' => (string) $line->varian->menu_id,
                        'qty' => (int) $line->qty,
                    ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Riwayat mutasi stok cabang untuk JS dashboard & halaman alert.
     * Format sama dengan demo lama: type masuk/keluar/waste, lines bersign.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mutations(int $cabangId): array
    {
        $rows = StockMovement::query()
            ->where('cabang_id', $cabangId)
            ->orderByDesc('tanggal')
            ->limit(500)
            ->get();

        // Satu laporan waste (beberapa bahan dalam satu pengajuan) = satu entri,
        // sama seperti data demo lama.
        return $rows
            ->groupBy(fn ($movement) => $movement->jenis_mutasi === 'waste'
                ? 'waste-' . $movement->tanggal->toDateTimeString() . '-' . $movement->keterangan
                : 'row-' . $movement->movement_id)
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'id' => (int) $group->min('movement_id'),
                    'date' => $first->tanggal->format('Y-m-d'),
                    'time' => $first->tanggal->format('H:i'),
                    'type' => match ($first->jenis_mutasi) {
                        'penerimaan_cabang' => 'masuk',
                        'waste' => 'waste',
                        default => 'keluar',
                    },
                    'ref' => $first->keterangan ?? '',
                    'lines' => $group->map(fn ($movement) => [
                        'item' => (string) $movement->bahan_id,
                        'amount' => in_array($movement->jenis_mutasi, ['penjualan', 'waste'], true)
                            ? -(float) $movement->jumlah
                            : (float) $movement->jumlah,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Surat jalan (pengiriman DC) untuk JS dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    private function shipments(int $cabangId): array
    {
        return SuratJalan::query()
            ->where('cabang_id', $cabangId)
            ->with(['detail', 'konfirmasi'])
            ->orderByDesc('surat_jalan_id')
            ->limit(100)
            ->get()
            ->map(fn ($sj) => [
                'no' => sprintf('#SJ-%03d', $sj->surat_jalan_id),
                'date' => $sj->tanggal_kirim?->format('d M Y') ?? '-',
                'origin' => 'Distribution Center',
                'store' => (string) $sj->cabang_id,
                'order' => null,
                'status' => $sj->computed_status,
                'items' => $sj->detail
                    ->map(fn ($detail) => [
                        'item' => (string) $detail->bahan_id,
                        'sent' => (int) $detail->qty_dikirim,
                        'received' => $detail->qty_diterima === null ? null : (int) $detail->qty_diterima,
                        'note' => null,
                    ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
