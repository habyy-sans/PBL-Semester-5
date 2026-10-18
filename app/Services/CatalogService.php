<?php

namespace App\Services;

use App\Models\BahanBaku;
use App\Models\MenuProduk;

/**
 * Payload katalog (items & menus) yang dikirim ke JavaScript halaman
 * Kasir. Bentuknya sama persis dengan config dummy lama supaya fungsi
 * JS (maxPortions, hpp, display, dst.) tidak perlu diubah.
 */
class CatalogService
{
    /**
     * Items (bahan) + stok cabang, keyed by bahan_id.
     * Dipakai POS dan Riwayat Transaksi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(?int $cabangId): array
    {
        $items = [];

        BahanBaku::query()
            ->with(['stokCabang' => fn ($query) => $cabangId !== null
                ? $query->where('cabang_id', $cabangId)
                : $query->whereRaw('1 = 0')])
            ->orderBy('bahan_id')
            ->get()
            ->each(function (BahanBaku $bahan) use (&$items) {
                $stok = $bahan->stokCabang->first();

                $items[$bahan->bahan_id] = [
                    'name' => $bahan->nama_bahan,
                    'category' => $bahan->kategori,
                    'pack' => $bahan->nama_kemasan,
                    'size' => (float) $bahan->isi_per_kemasan,
                    'unit' => $bahan->satuan,
                    'min' => (float) ($stok->stok_minimum ?? 0),
                    'price' => (float) $bahan->harga_beli,
                    'stock' => (float) ($stok->jumlah_stok ?? 0),
                ];
            });

        return $items;
    }

    /**
     * Menus + resep varian default, keyed by menu_id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function menus(): array
    {
        $menus = [];

        MenuProduk::query()
            ->with(['kategori', 'varian.resep'])
            ->orderBy('menu_id')
            ->get()
            ->each(function (MenuProduk $menu) use (&$menus) {
                $recipe = [];

                // Varian default ("Regular") memegang resep/SOP.
                $varian = $menu->varian->first();

                if ($varian !== null) {
                    foreach ($varian->resep as $resep) {
                        $recipe[$resep->bahan_id] = (float) $resep->jumlah_bahan;
                    }
                }

                $menus[$menu->menu_id] = [
                    'name' => $menu->nama_menu,
                    'category' => $menu->kategori->nama_kategori,
                    'emoji' => $menu->emoji,
                    'price' => (float) $menu->harga_jual,
                    'recipe' => (object) $recipe,
                ];
            });

        return $menus;
    }
}
