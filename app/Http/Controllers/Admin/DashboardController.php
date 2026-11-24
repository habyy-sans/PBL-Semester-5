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
use App\Models\VarianMenu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $criticalBranchQuery = StokCabang::query()->where(function (Builder $query): void {
            $query->where('jumlah_stok', '<=', 0)
                ->orWhereColumn('jumlah_stok', '<', 'stok_minimum');
        });

        $criticalDcQuery = StokDc::query()->where(function (Builder $query): void {
            $query->where('jumlah_stok', '<=', 0)
                ->orWhereColumn('jumlah_stok', '<', 'stok_minimum');
        });

        $lowBranchStock = (clone $criticalBranchQuery)
            ->with(['cabang', 'bahan'])
            ->orderBy('jumlah_stok')
            ->limit(10)
            ->get()
            ->map(function (StokCabang $stock): array {
                $qty = (float) $stock->jumlah_stok;
                $min = (float) $stock->stok_minimum;
                $isEmpty = $qty <= 0;

                return [
                    'lokasi' => $stock->cabang?->nama_cabang ?? '-',
                    'lokasi_label' => 'Cabang',
                    'bahan' => $stock->bahan?->nama_bahan ?? '-',
                    'kategori' => $stock->bahan?->kategori ?? '',
                    'jumlah' => $qty,
                    'minimum' => $min,
                    'satuan' => $stock->bahan?->satuan ?? '',
                    'is_empty' => $isEmpty,
                    'ratio' => $isEmpty ? -1.0 : ($min > 0 ? $qty / $min : 1.0),
                    'manage_url' => route('admin.stok-cabang.index', [
                        'cabang_id' => $stock->cabang_id,
                        'status' => $isEmpty ? 'habis' : 'menipis',
                    ]),
                ];
            });

        $lowDcStock = (clone $criticalDcQuery)
            ->with(['dc', 'bahan'])
            ->orderBy('jumlah_stok')
            ->limit(10)
            ->get()
            ->map(function (StokDc $stock): array {
                $qty = (float) $stock->jumlah_stok;
                $min = (float) $stock->stok_minimum;
                $isEmpty = $qty <= 0;

                return [
                    'lokasi' => $stock->dc?->nama_dc ?? '-',
                    'lokasi_label' => 'DC',
                    'bahan' => $stock->bahan?->nama_bahan ?? '-',
                    'kategori' => $stock->bahan?->kategori ?? '',
                    'jumlah' => $qty,
                    'minimum' => $min,
                    'satuan' => $stock->bahan?->satuan ?? '',
                    'is_empty' => $isEmpty,
                    'ratio' => $isEmpty ? -1.0 : ($min > 0 ? $qty / $min : 1.0),
                    'manage_url' => route('admin.stok-dc.index', [
                        'dc_id' => $stock->dc_id,
                        'status' => $isEmpty ? 'habis' : 'menipis',
                    ]),
                ];
            });

        $habisCount = StokCabang::query()->where('jumlah_stok', '<=', 0)->count()
            + StokDc::query()->where('jumlah_stok', '<=', 0)->count();

        $menipisCount = StokCabang::query()
            ->where('jumlah_stok', '>', 0)
            ->whereColumn('jumlah_stok', '<', 'stok_minimum')
            ->count()
            + StokDc::query()
                ->where('jumlah_stok', '>', 0)
                ->whereColumn('jumlah_stok', '<', 'stok_minimum')
                ->count();

        $branchesWithoutManager = Cabang::query()
            ->whereDoesntHave('pengguna', function (Builder $builder): void {
                $builder->where('role', config('menu.roles.manager.db'))
                    ->where('status', 'aktif');
            })
            ->orderBy('nama_cabang')
            ->get(['cabang_id', 'nama_cabang']);

        $menusWithoutVariant = MenuProduk::query()
            ->doesntHave('varian')
            ->orderBy('nama_menu')
            ->get(['menu_id', 'nama_menu']);

        $variantsWithoutRecipe = VarianMenu::query()
            ->with('menu:menu_id,nama_menu')
            ->doesntHave('resep')
            ->orderBy('varian_id')
            ->get(['varian_id', 'menu_id', 'nama_varian']);

        return view('admin.dashboard.index', [
            'summary' => [
                'users' => Pengguna::query()->count(),
                'active_users' => Pengguna::query()->where('status', 'aktif')->count(),
                'cabang' => Cabang::query()->count(),
                'dc' => Dc::query()->count(),
                'bahan' => BahanBaku::query()->count(),
                'menu' => MenuProduk::query()->count(),
                'habis_stock' => $habisCount,
                'menipis_stock' => $menipisCount,
                'low_stock' => $habisCount + $menipisCount,
            ],
            'actionItems' => [
                'branches_without_manager' => $branchesWithoutManager,
                'menus_without_variant' => $menusWithoutVariant,
                'variants_without_recipe' => $variantsWithoutRecipe,
            ],
            'lowStock' => $lowBranchStock
                ->concat($lowDcStock)
                ->sort(fn (array $a, array $b): int => [$a['ratio'], $a['jumlah']] <=> [$b['ratio'], $b['jumlah']])
                ->take(12)
                ->values(),
        ]);
    }
}
