<?php

namespace Database\Seeders;

use App\Models\Cabang;
use Illuminate\Database\Seeder;

class CabangSeeder extends Seeder
{
    /**
     * Seed data cabang. "Store" di data demo didefinisikan sebagai baris
     * cabang di database (skema datar, tanpa level store).
     */
    public function run(): void
    {
        $cabang = [
            ['nama_cabang' => 'Malang · Store 1', 'alamat' => 'Jl. Dinoyo No. 1, Malang', 'telepon' => '0341-555-0001'],
            ['nama_cabang' => 'Malang · Store 2', 'alamat' => 'Jl. Soekarno Hatta No. 2, Malang', 'telepon' => '0341-555-0002'],
            ['nama_cabang' => 'Malang · Store 3', 'alamat' => 'Jl. Kawi No. 3, Malang', 'telepon' => '0341-555-0003'],
        ];

        foreach ($cabang as $item) {
            Cabang::query()->updateOrCreate(
                ['nama_cabang' => $item['nama_cabang']],
                $item,
            );
        }
    }
}
