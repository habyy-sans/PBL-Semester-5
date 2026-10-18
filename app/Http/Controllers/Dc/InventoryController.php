<?php

namespace App\Http\Controllers\Dc;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\StokCabang;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * Halaman Stock DC — pantau stok semua cabang.
     *
     * Baris = semua kombinasi cabang × bahan, sama seperti data dummy
     * lama, dengan stok/minimum dari stok_cabang per cabang.
     */
    public function index(Request $request): View
    {
        $cabangList = Cabang::query()->orderBy('cabang_id')->get();

        $storeList = $cabangList
            ->map(fn (Cabang $cabang) => [
                'id' => (string) $cabang->cabang_id,
                'name' => $cabang->nama_cabang,
            ])
            ->all();

        $stokMap = StokCabang::query()
            ->with('bahan')
            ->get()
            ->groupBy('cabang_id');

        $rows = [];

        foreach ($cabangList as $cabang) {
            foreach ($stokMap->get($cabang->cabang_id, collect())->sortBy('bahan_id') as $stok) {
                $bahan = $stok->bahan;

                $rows[] = [
                    'key' => $cabang->cabang_id.'|'.$bahan->bahan_id,
                    'store' => (string) $cabang->cabang_id,
                    'storeName' => $cabang->nama_cabang,
                    'stock' => (float) $stok->jumlah_stok,
                    'name' => $bahan->nama_bahan,
                    'category' => $bahan->kategori,
                    'pack' => $bahan->nama_kemasan,
                    'size' => (float) $bahan->isi_per_kemasan,
                    'unit' => $bahan->satuan,
                    'min' => (float) $stok->stok_minimum,
                ];
            }
        }

        return view('dc.inventory.index', [
            'storeList' => $storeList,
            'rows' => $rows,
        ]);
    }
}
