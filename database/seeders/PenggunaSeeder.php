<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Dc;
use App\Models\Pengguna;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PenggunaSeeder extends Seeder
{
    /**
     * Seed pengguna dari data demo (config/demo_user.list).
     *
     * - Role pendek (kasir, ic, manager, dc, admin) dipetakan ke nilai
     *   kolom pengguna.role lewat config('menu.roles.*.db').
     * - 'store' di data demo dipetakan ke cabang dengan nama yang sama
     *   dengan hasil CabangSeeder ("Malang · Store 1", dst.).
     * - Password semua akun demo: password123 (hash bcrypt).
     */
    public function run(): void
    {
        $password = Hash::make('password123');

        foreach (config('demo_user.list') as $item) {
            $roleDb = config("menu.roles.{$item['role']}.db");

            abort_if($roleDb === null, 500, "Role demo '{$item['role']}' tidak punya mapping 'db' di config/menu.php.");

            $cabangId = null;
            $dcId = null;

            if ($item['store'] !== null) {
                $cabang = Cabang::query()
                    ->where('nama_cabang', 'Malang · Store '.substr($item['store'], -1))
                    ->first();

                abort_if($cabang === null, 500, "Cabang untuk store demo '{$item['store']}' tidak ditemukan. Jalankan CabangSeeder dulu.");

                $cabangId = $cabang->cabang_id;
            }

            if ($item['role'] === 'dc') {
                $dcId = Dc::query()->where('nama_dc', 'Distribution Center')->first()?->dc_id;

                abort_if($dcId === null, 500, 'DC "Distribution Center" tidak ditemukan. Jalankan DcSeeder dulu.');
            }

            Pengguna::query()->updateOrCreate(
                ['email' => $item['email']],
                [
                    'nama_lengkap' => $item['name'],
                    'username' => strstr($item['email'], '@', true),
                    'password_hash' => $password,
                    'role' => $roleDb,
                    'cabang_id' => $cabangId,
                    'dc_id' => $dcId,
                    'status' => $item['status'],
                ],
            );
        }
    }
}
