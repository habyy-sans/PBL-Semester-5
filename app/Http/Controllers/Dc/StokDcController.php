<?php

namespace App\Http\Controllers\Dc;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\StockMovement;
use App\Models\StokDc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StokDcController extends Controller
{
    private function dcId(): int
    {
        $dcId = auth()->user()?->dc_id;

        abort_if(!$dcId, 403, 'Akun tidak terhubung dengan Distribution Center.');

        return (int) $dcId;
    }

    /**
     * Tampilkan daftar stok bahan baku fisik di gudang DC ini.
     * Katalog master bahan baku didefinisikan oleh Admin, DC hanya memantau stok dan menerima barang.
     */
    public function index(): View
    {
        $dcId = $this->dcId();

        $bahanList = BahanBaku::query()
            ->with(['stokDc' => fn ($query) => $query->where('dc_id', $dcId)])
            ->orderBy('nama_bahan')
            ->get();

        return view('dc.bahan-baku.index', compact('bahanList'));
    }

    /**
     * Catat penambahan stok fisik masuk (inbound restock) ke gudang DC.
     */
    public function addStock(Request $request, BahanBaku $bahanBaku): RedirectResponse
    {
        $dcId = $this->dcId();

        $data = $request->validate([
            'jumlah' => ['required', 'numeric', 'gt:0'],
        ]);

        DB::transaction(function () use ($data, $dcId, $bahanBaku) {
            $stok = StokDc::where('dc_id', $dcId)
                ->where('bahan_id', $bahanBaku->bahan_id)
                ->lockForUpdate()
                ->first();

            if (! $stok) {
                $stok = StokDc::create([
                    'dc_id' => $dcId,
                    'bahan_id' => $bahanBaku->bahan_id,
                    'jumlah_stok' => 0,
                    'stok_minimum' => 0,
                ]);
                $stok = StokDc::where('dc_id', $dcId)
                    ->where('bahan_id', $bahanBaku->bahan_id)
                    ->lockForUpdate()
                    ->first();
            }

            $stok->jumlah_stok = (float) $stok->jumlah_stok + (float) $data['jumlah'];
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
            ->route('dc.stok-dc.index')
            ->with('success', "Stok {$bahanBaku->nama_bahan} berhasil ditambahkan ke gudang DC.");
    }
}
