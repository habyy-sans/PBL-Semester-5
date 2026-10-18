<?php

namespace Database\Seeders;

use App\Models\Dc;
use Illuminate\Database\Seeder;

class DcSeeder extends Seeder
{
    /**
     * Seed data Distribution Center.
     */
    public function run(): void
    {
        Dc::query()->updateOrCreate(
            ['nama_dc' => 'Distribution Center'],
            ['alamat' => 'Jl. Raya Purwodadi No. 10, Pasuruan', 'telepon' => '0343-555-0100'],
        );
    }
}
