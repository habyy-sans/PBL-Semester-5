<?php

namespace App\Services;

use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\Dc;
use App\Models\MenuProduk;
use App\Models\ResepMenu;
use App\Models\StokCabang;
use App\Models\StokDc;
use App\Models\VarianMenu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminMasterSyncService
{
    /**
     * Inisialisasi baris stok awal (0/0) untuk cabang baru pada seluruh bahan baku.
     */
    public function initializeStockForBranch(Cabang $cabang): int
    {
        $bahanIds = BahanBaku::query()->pluck('bahan_id');

        if ($bahanIds->isEmpty()) {
            return 0;
        }

        $rows = $bahanIds->map(fn (int $bahanId): array => [
            'cabang_id' => $cabang->cabang_id,
            'bahan_id' => $bahanId,
            'jumlah_stok' => 0,
            'stok_minimum' => 0,
        ])->all();

        return DB::table('stok_cabang')->insertOrIgnore($rows);
    }

    /**
     * Inisialisasi baris stok awal (0/0) untuk DC baru pada seluruh bahan baku.
     */
    public function initializeStockForDc(Dc $dc): int
    {
        $bahanIds = BahanBaku::query()->pluck('bahan_id');

        if ($bahanIds->isEmpty()) {
            return 0;
        }

        $rows = $bahanIds->map(fn (int $bahanId): array => [
            'dc_id' => $dc->dc_id,
            'bahan_id' => $bahanId,
            'jumlah_stok' => 0,
            'stok_minimum' => 0,
        ])->all();

        return DB::table('stok_dc')->insertOrIgnore($rows);
    }

    /**
     * Inisialisasi baris stok awal (0/0) untuk bahan baku baru di seluruh cabang dan DC.
     *
     * @return array{cabang: int, dc: int}
     */
    public function initializeStockForIngredient(BahanBaku $bahan): array
    {
        $cabangIds = Cabang::query()->pluck('cabang_id');
        $dcIds = Dc::query()->pluck('dc_id');

        $cabangInserted = 0;
        if ($cabangIds->isNotEmpty()) {
            $cabangRows = $cabangIds->map(fn (int $cabangId): array => [
                'cabang_id' => $cabangId,
                'bahan_id' => $bahan->bahan_id,
                'jumlah_stok' => 0,
                'stok_minimum' => 0,
            ])->all();

            $cabangInserted = DB::table('stok_cabang')->insertOrIgnore($cabangRows);
        }

        $dcInserted = 0;
        if ($dcIds->isNotEmpty()) {
            $dcRows = $dcIds->map(fn (int $dcId): array => [
                'dc_id' => $dcId,
                'bahan_id' => $bahan->bahan_id,
                'jumlah_stok' => 0,
                'stok_minimum' => 0,
            ])->all();

            $dcInserted = DB::table('stok_dc')->insertOrIgnore($dcRows);
        }

        return [
            'cabang' => $cabangInserted,
            'dc' => $dcInserted,
        ];
    }

    /**
     * Buat varian default "Regular" (harga tambahan 0) agar menu langsung siap dipasangi resep.
     */
    public function createDefaultVariantForMenu(MenuProduk $menu): VarianMenu
    {
        return VarianMenu::query()->firstOrCreate(
            [
                'menu_id' => $menu->menu_id,
                'nama_varian' => 'Regular',
            ],
            [
                'harga_tambahan' => 0,
            ]
        );
    }

    public function hasActiveStockForBranch(Cabang $cabang): bool
    {
        return StokCabang::query()
            ->where('cabang_id', $cabang->cabang_id)
            ->where(function (Builder $query): void {
                $query->where('jumlah_stok', '>', 0)
                    ->orWhere('stok_minimum', '>', 0);
            })
            ->exists();
    }

    public function hasActiveStockForDc(Dc $dc): bool
    {
        return StokDc::query()
            ->where('dc_id', $dc->dc_id)
            ->where(function (Builder $query): void {
                $query->where('jumlah_stok', '>', 0)
                    ->orWhere('stok_minimum', '>', 0);
            })
            ->exists();
    }

    public function hasActiveStockForIngredient(BahanBaku $bahan): bool
    {
        $branchHasActive = StokCabang::query()
            ->where('bahan_id', $bahan->bahan_id)
            ->where(function (Builder $query): void {
                $query->where('jumlah_stok', '>', 0)
                    ->orWhere('stok_minimum', '>', 0);
            })
            ->exists();

        if ($branchHasActive) {
            return true;
        }

        return StokDc::query()
            ->where('bahan_id', $bahan->bahan_id)
            ->where(function (Builder $query): void {
                $query->where('jumlah_stok', '>', 0)
                    ->orWhere('stok_minimum', '>', 0);
            })
            ->exists();
    }

    public function pruneZeroStockForBranch(Cabang $cabang): void
    {
        StokCabang::query()
            ->where('cabang_id', $cabang->cabang_id)
            ->where('jumlah_stok', '<=', 0)
            ->where('stok_minimum', '<=', 0)
            ->delete();
    }

    public function pruneZeroStockForDc(Dc $dc): void
    {
        StokDc::query()
            ->where('dc_id', $dc->dc_id)
            ->where('jumlah_stok', '<=', 0)
            ->where('stok_minimum', '<=', 0)
            ->delete();
    }

    public function pruneZeroStockForIngredient(BahanBaku $bahan): void
    {
        StokCabang::query()
            ->where('bahan_id', $bahan->bahan_id)
            ->where('jumlah_stok', '<=', 0)
            ->where('stok_minimum', '<=', 0)
            ->delete();

        StokDc::query()
            ->where('bahan_id', $bahan->bahan_id)
            ->where('jumlah_stok', '<=', 0)
            ->where('stok_minimum', '<=', 0)
            ->delete();
    }

    /**
     * Sinkronkan seluruh komposisi bahan baku untuk satu varian menu secara atomik.
     *
     * @param  array<int, array{bahan_id: int|string, jumlah_bahan: float|int|string}>  $items
     */
    public function syncRecipeForVariant(VarianMenu $varian, array $items): int
    {
        $normalized = [];

        foreach ($items as $item) {
            $bahanId = (int) $item['bahan_id'];
            $normalized[$bahanId] = (float) $item['jumlah_bahan'];
        }

        return DB::transaction(function () use ($varian, $normalized): int {
            $existingIds = ResepMenu::query()
                ->where('varian_id', $varian->varian_id)
                ->lockForUpdate()
                ->pluck('bahan_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            $toDelete = array_diff($existingIds, array_keys($normalized));

            if ($toDelete !== []) {
                ResepMenu::query()
                    ->where('varian_id', $varian->varian_id)
                    ->whereIn('bahan_id', $toDelete)
                    ->delete();
            }

            foreach ($normalized as $bahanId => $qty) {
                ResepMenu::query()->updateOrCreate(
                    [
                        'varian_id' => $varian->varian_id,
                        'bahan_id' => $bahanId,
                    ],
                    [
                        'jumlah_bahan' => $qty,
                    ],
                );
            }

            return count($normalized);
        });
    }

    public function clearRecipeForVariant(VarianMenu $varian): int
    {
        return DB::transaction(function () use ($varian): int {
            return ResepMenu::query()
                ->where('varian_id', $varian->varian_id)
                ->delete();
        });
    }

    public function calculateVariantHpp(VarianMenu $varian): float
    {
        $total = 0.0;

        foreach ($varian->resep as $resep) {
            $bahan = $resep->bahan;
            if ($bahan === null) {
                continue;
            }

            $unitCost = (float) $bahan->harga_beli / max((float) $bahan->isi_per_kemasan, 1.0);
            $total += (float) $resep->jumlah_bahan * $unitCost;
        }

        return $total;
    }

    /**
     * Katalog bahan baku terurut beserta biaya per satuan dasar untuk komponen Recipe Builder.
     *
     * @return array<int, array{bahan_id: int, nama_bahan: string, kategori: string, satuan: string, unit_cost: float}>
     */
    public function ingredientCatalogPayload(): array
    {
        return BahanBaku::query()
            ->orderBy('nama_bahan')
            ->get(['bahan_id', 'nama_bahan', 'kategori', 'satuan', 'harga_beli', 'isi_per_kemasan'])
            ->map(fn (BahanBaku $bahan): array => [
                'bahan_id' => (int) $bahan->bahan_id,
                'nama_bahan' => $bahan->nama_bahan,
                'kategori' => $bahan->kategori ?? '',
                'satuan' => $bahan->satuan,
                'unit_cost' => (float) $bahan->harga_beli / max((float) $bahan->isi_per_kemasan, 1.0),
            ])
            ->values()
            ->all();
    }
}
