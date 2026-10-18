<?php

namespace Database\Seeders;

use App\Models\BahanBaku;
use App\Models\KategoriMenu;
use App\Models\MenuProduk;
use App\Models\ResepMenu;
use App\Models\VarianMenu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Seed kategori menu, menu produk, varian default ("Regular"),
     * dan resep per varian dari data demo (config/demo.menus).
     *
     * resep_menu terikat ke varian, jadi tiap menu diberi satu varian
     * default agar resep (SOP) bisa disimpan.
     */
    public function run(): void
    {
        foreach (config('demo.menus') as $menu) {
            $kategori = KategoriMenu::query()->firstOrCreate(
                ['nama_kategori' => $menu['category']],
            );

            $produk = MenuProduk::query()->updateOrCreate(
                ['nama_menu' => $menu['name']],
                [
                    'kategori_menu_id' => $kategori->kategori_menu_id,
                    'harga_jual' => $menu['price'],
                    'emoji' => $menu['emoji'],
                ],
            );

            $varian = VarianMenu::query()->updateOrCreate(
                ['menu_id' => $produk->menu_id, 'nama_varian' => 'Regular'],
                ['harga_tambahan' => 0],
            );

            // Sinkronkan resep: hapus lama, isi ulang dari data demo.
            ResepMenu::query()->where('varian_id', $varian->varian_id)->delete();

            foreach ($menu['recipe'] as $itemKey => $amount) {
                $bahan = BahanBaku::query()
                    ->where('nama_bahan', config("demo.items.$itemKey.name"))
                    ->first();

                abort_if($bahan === null, 500, "Bahan demo '$itemKey' belum di-seed. Jalankan BahanBakuSeeder dulu.");

                ResepMenu::query()->create([
                    'varian_id' => $varian->varian_id,
                    'bahan_id' => $bahan->bahan_id,
                    'jumlah_bahan' => $amount,
                ]);
            }
        }
    }
}
