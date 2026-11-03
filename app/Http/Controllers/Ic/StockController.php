<?php

namespace App\Http\Controllers\Ic;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use Illuminate\Http\JsonResponse;
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
        return view('ic.stock.index', [
            'stockItems' => $this->stockItems($request),
        ]);
    }

    /**
     * Data stok terbaru (JSON) untuk pembaruan otomatis di halaman Stok Store.
     */
    public function data(Request $request): JsonResponse
    {
        return response()
            ->json($this->stockItems($request))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stockItems(Request $request): array
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
                    // Kolom kategori, nama_kemasan, isi_per_kemasan boleh NULL di database;
                    // beri nilai cadangan supaya tampilan tidak menulis "null".
                    'category' => $bahan->kategori ?: 'Lainnya',
                    'pack' => $bahan->nama_kemasan ?: $bahan->satuan,
                    'size' => (float) ($bahan->isi_per_kemasan ?: 1),
                    'unit' => $bahan->satuan,
                    'min' => (float) ($stok->stok_minimum ?? 0),
                    'price' => (float) $bahan->harga_beli,
                ];
            });

        return $stockItems;
    }
}