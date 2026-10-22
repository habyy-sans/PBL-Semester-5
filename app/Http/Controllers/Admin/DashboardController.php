<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\Dc;
use App\Models\MenuProduk;
use App\Models\Pengguna;
use App\Models\StokCabang;
use App\Models\StokDc;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $lowBranchStock = StokCabang::query()
            ->whereColumn('jumlah_stok', '<', 'stok_minimum')
            ->with(['cabang', 'bahan'])
            ->orderBy('jumlah_stok')
            ->limit(8)
            ->get()
            ->map(fn (StokCabang $stock) => [
                'lokasi' => $stock->cabang->nama_cabang,
                'lokasi_label' => 'Cabang',
                'bahan' => $stock->bahan->nama_bahan,
                'jumlah' => $stock->jumlah_stok,
                'minimum' => $stock->stok_minimum,
                'satuan' => $stock->bahan->satuan,
            ]);

        $lowDcStock = StokDc::query()
            ->whereColumn('jumlah_stok', '<', 'stok_minimum')
            ->with(['dc', 'bahan'])
            ->orderBy('jumlah_stok')
            ->limit(8)
            ->get()
            ->map(fn (StokDc $stock) => [
                'lokasi' => $stock->dc->nama_dc,
                'lokasi_label' => 'DC',
                'bahan' => $stock->bahan->nama_bahan,
                'jumlah' => $stock->jumlah_stok,
                'minimum' => $stock->stok_minimum,
                'satuan' => $stock->bahan->satuan,
            ]);

        return view('admin.dashboard.index', [
            'summary' => [
                'users' => Pengguna::query()->count(),
                'cabang' => Cabang::query()->count(),
                'dc' => Dc::query()->count(),
                'bahan' => BahanBaku::query()->count(),
                'menu' => MenuProduk::query()->count(),
                'low_stock' => StokCabang::query()->whereColumn('jumlah_stok', '<', 'stok_minimum')->count()
                    + StokDc::query()->whereColumn('jumlah_stok', '<', 'stok_minimum')->count(),
            ],
            'lowStock' => $lowBranchStock
                ->concat($lowDcStock)
                ->sortBy('jumlah')
                ->take(12)
                ->values(),
        ]);
    }
}
