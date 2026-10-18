<?php

namespace Database\Seeders;

use App\Models\BahanBaku;
use App\Models\Dc;
use App\Models\StokDc;
use Illuminate\Database\Seeder;

class StokDcSeeder extends Seeder
{
    /**
     * Seed stok DC dari data demo (config/demo.dc_stock).
     *
     * Data demo menyimpan stok DC dalam KEMASAN; disimpan di database
     * dalam satuan terkecil (kemasan × isi_per_kemasan) agar konsisten
     * dengan stok_cabang. Minimum DC tidak ada di data demo -> 0.
     */
    public function run(): void
    {
        $dc = Dc::query()->where('nama_dc', 'Distribution Center')->first();

        abort_if($dc === null, 500, 'DC "Distribution Center" tidak ditemukan. Jalankan DcSeeder dulu.');

        foreach (config('demo.dc_stock') as $itemKey => $packs) {
            $bahan = BahanBaku::query()
                ->where('nama_bahan', config("demo.items.$itemKey.name"))
                ->first();

            abort_if($bahan === null, 500, "Bahan demo '$itemKey' belum di-seed. Jalankan BahanBakuSeeder dulu.");

            StokDc::query()->updateOrCreate(
                ['dc_id' => $dc->dc_id, 'bahan_id' => $bahan->bahan_id],
                [
                    'jumlah_stok' => $packs * (float) $bahan->isi_per_kemasan,
                    'stok_minimum' => 0,
                ],
            );
        }
    }
}
