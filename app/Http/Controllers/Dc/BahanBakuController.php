<?php

namespace App\Http\Controllers\Dc;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\StokDc;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BahanBakuController extends Controller
{
    private function dcId(): int
    {
        $dcId = auth()->user()->dc_id;

        abort_if(!$dcId, 403, 'Akun tidak terhubung dengan DC.');

        return (int) $dcId;
    }

    public function index(): View
    {
        $dcId = $this->dcId();

        $bahanList = BahanBaku::query()
            ->with(['stokDc' => fn ($query) => $query->where('dc_id', $dcId)])
            ->orderBy('nama_bahan')
            ->get();

        return view('dc.bahan-baku.index', compact('bahanList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $dcId = $this->dcId();

        $data = $request->validate([
            'nama_bahan'      => ['required', 'string', 'max:150'],
            'kategori'        => ['required', 'string', 'max:100'],
            'satuan'          => ['required', 'string', 'max:30'],
            'nama_kemasan'    => ['required', 'string', 'max:50'],
            'isi_per_kemasan' => ['required', 'numeric', 'gt:0'],
            'harga_beli'      => ['required', 'numeric', 'gte:0'],
            'stok_awal'       => ['required', 'numeric', 'gte:0'],
            'stok_minimum'    => ['required', 'numeric', 'gte:0'],
        ]);

        DB::transaction(function () use ($data, $dcId) {
            $bahan = BahanBaku::create([
                'nama_bahan'      => $data['nama_bahan'],
                'kategori'        => $data['kategori'],
                'satuan'          => $data['satuan'],
                'nama_kemasan'    => $data['nama_kemasan'],
                'isi_per_kemasan' => $data['isi_per_kemasan'],
                'harga_beli'      => $data['harga_beli'],
            ]);

            StokDc::create([
                'dc_id'        => $dcId,
                'bahan_id'     => $bahan->bahan_id,
                'jumlah_stok'  => $data['stok_awal'],
                'stok_minimum' => $data['stok_minimum'],
            ]);

            if ((float) $data['stok_awal'] > 0) {
                StockMovement::create([
                    'bahan_id'     => $bahan->bahan_id,
                    'cabang_id'    => null,
                    'dc_id'        => $dcId,
                    'jenis_mutasi' => 'penerimaan_dc',
                    'jumlah'       => $data['stok_awal'],
                    'referensi_id' => null,
                    'tanggal'      => now(),
                ]);
            }
        });

        return redirect()
            ->route('dc.bahan-baku.index')
            ->with('success', 'Bahan baku berhasil ditambahkan.');
    }

    public function update(Request $request, BahanBaku $bahanBaku): RedirectResponse
    {
        $this->dcId();

        $data = $request->validate([
            'nama_bahan'      => ['required', 'string', 'max:150'],
            'kategori'        => ['required', 'string', 'max:100'],
            'satuan'          => ['required', 'string', 'max:30'],
            'nama_kemasan'    => ['required', 'string', 'max:50'],
            'isi_per_kemasan' => ['required', 'numeric', 'gt:0'],
            'harga_beli'      => ['required', 'numeric', 'gte:0'],
        ]);

        $bahanBaku->update($data);

        return redirect()
            ->route('dc.bahan-baku.index')
            ->with('success', 'Informasi bahan baku berhasil diperbarui.');
    }

    public function addStock(Request $request, BahanBaku $bahanBaku): RedirectResponse
    {
        $dcId = $this->dcId();

        $data = $request->validate([
            'jumlah' => ['required', 'numeric', 'gt:0'],
        ]);

        DB::transaction(function () use ($data, $dcId, $bahanBaku) {
            $stok = StokDc::query()
                ->where('dc_id', $dcId)
                ->where('bahan_id', $bahanBaku->bahan_id)
                ->lockForUpdate()
                ->first();

            if (!$stok) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Catatan stok DC untuk bahan ini tidak ditemukan.',
                ]);
            }

            $stok->jumlah_stok = (float) $stok->jumlah_stok
                + (float) $data['jumlah'];
            $stok->save();

            StockMovement::create([
                'bahan_id'     => $bahanBaku->bahan_id,
                'cabang_id'    => null,
                'dc_id'        => $dcId,
                'jenis_mutasi' => 'penerimaan_dc',
                'jumlah'       => $data['jumlah'],
                'referensi_id' => null,
                'tanggal'      => now(),
            ]);
        });

        return redirect()
            ->route('dc.bahan-baku.index')
            ->with('success', 'Stok DC berhasil ditambahkan.');
    }
}