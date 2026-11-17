<?php

namespace App\Http\Controllers\Dc;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\StokCabang;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * Monitoring stok seluruh cabang DC.
     * Setiap cabang memiliki tabel tersendiri.
     */
    public function index(Request $request): View
    {
        $cabangList = Cabang::query()
            ->orderBy('cabang_id')
            ->get();

        $stokMap = StokCabang::query()
            ->with('bahan')
            ->get()
            ->groupBy('cabang_id');

        $branches = $cabangList->map(function (Cabang $cabang) use ($stokMap) {
            $rows = $stokMap
                ->get($cabang->cabang_id, collect())
                ->filter(fn ($stok) => $stok->bahan !== null)
                ->sortBy(fn ($stok) => $stok->bahan->nama_bahan)
                ->map(function ($stok) use ($cabang) {
                    $bahan = $stok->bahan;

                    return [
                        'key' => $cabang->cabang_id . '|' . $bahan->bahan_id,
                        'store' => (string) $cabang->cabang_id,
                        'storeName' => $cabang->nama_cabang,
                        'bahanId' => (string) $bahan->bahan_id,
                        'stock' => (float) $stok->jumlah_stok,
                        'name' => $bahan->nama_bahan,
                        'category' => $bahan->kategori,
                        'pack' => $bahan->nama_kemasan,
                        'size' => (float) $bahan->isi_per_kemasan,
                        'unit' => $bahan->satuan,
                        'min' => (float) $stok->stok_minimum,
                    ];
                })
                ->values()
                ->all();

            return [
                'id' => (string) $cabang->cabang_id,
                'name' => $cabang->nama_cabang,
                'rows' => $rows,
            ];
        })->values()->all();

        $bahanList = BahanBaku::query()
            ->orderBy('nama_bahan')
            ->get(['bahan_id', 'nama_bahan'])
            ->map(fn (BahanBaku $bahan) => [
                'id' => (string) $bahan->bahan_id,
                'name' => $bahan->nama_bahan,
            ])
            ->values()
            ->all();

        return view('dc.inventory.index', compact(
            'branches',
            'bahanList'
        ));
    }
}
