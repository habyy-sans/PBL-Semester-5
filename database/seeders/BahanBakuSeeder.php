<?php

namespace Database\Seeders;

use App\Models\BahanBaku;
use Illuminate\Database\Seeder;

class BahanBakuSeeder extends Seeder
{
    /**
     * Seed bahan baku dari data demo (config/demo.items).
     *
     * Pemetaan: pack -> nama_kemasan, size -> isi_per_kemasan,
     * unit -> satuan, price -> harga_beli, category -> kategori.
     */
    public function run(): void
    {
        foreach (config('demo.items') as $item) {
            BahanBaku::query()->updateOrCreate(
                ['nama_bahan' => $item['name']],
                [
                    'kategori' => $item['category'],
                    'satuan' => $item['unit'],
                    'nama_kemasan' => $item['pack'],
                    'isi_per_kemasan' => $item['size'],
                    'harga_beli' => $item['price'],
                ],
            );
        }
    }
}
