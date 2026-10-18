<?php

namespace Database\Seeders;

use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\StokCabang;
use Illuminate\Database\Seeder;

class StokCabangSeeder extends Seeder
{
    /**
     * Seed stok awal tiap cabang dari data demo (config/demo.stocks).
     * Jumlah stok dalam satuan terkecil; stok_minimum dari 'min' item demo.
     */
    public function run(): void
    {
        foreach (config('demo.stocks') as $storeKey => $stocks) {
            $cabangName = config("demo.stores.$storeKey.name");

            $cabang = Cabang::query()->where('nama_cabang', $cabangName)->first();

            abort_if($cabang === null, 500, "Cabang '$cabangName' tidak ditemukan. Jalankan CabangSeeder dulu.");

            foreach ($stocks as $itemKey => $qty) {
                $bahan = BahanBaku::query()
                    ->where('nama_bahan', config("demo.items.$itemKey.name"))
                    ->first();

                abort_if($bahan === null, 500, "Bahan demo '$itemKey' belum di-seed. Jalankan BahanBakuSeeder dulu.");

                StokCabang::query()->updateOrCreate(
                    ['cabang_id' => $cabang->cabang_id, 'bahan_id' => $bahan->bahan_id],
                    [
                        'jumlah_stok' => $qty,
                        'stok_minimum' => config("demo.items.$itemKey.min"),
                    ],
                );
            }
        }
    }
}
