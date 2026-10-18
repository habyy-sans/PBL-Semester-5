<?php

namespace App\Http\Controllers\Ic;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    /**
     * Halaman Stok Store (IC).
     *
     * Stok dan minimum dalam satuan terkecil, dalam satuan kemasan
     * ditampilkan oleh JS di view (contoh: 3430 ml => "4 Botol 630 ml").
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $stockItems = [];

        BahanBaku::query()
            ->with(['stokCabang' => fn ($query) => $query->where('cabang_id', $user->cabang_id)])
            ->orderBy('bahan_id')
            ->get()
            ->each(function (BahanBaku $bahan) use (&$stockItems) {
                $stok = $bahan->stokCabang->first();

                $stockItems[] = [
                    'id' => (string) $bahan->bahan_id,
                    'stock' => (float) ($stok->jumlah_stok ?? 0),
                    'name' => $bahan->nama_bahan,
                    'category' => $bahan->kategori,
                    'pack' => $bahan->nama_kemasan,
                    'size' => (float) $bahan->isi_per_kemasan,
                    'unit' => $bahan->satuan,
                    'min' => (float) ($stok->stok_minimum ?? 0),
                    'price' => (float) $bahan->harga_beli,
                ];
            });

        return view('ic.stock.index', [
            'stockItems' => $stockItems,
        ]);
    }
}
