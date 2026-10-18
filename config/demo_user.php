<?php

/*
|--------------------------------------------------------------------------
| DATA DUMMY PENGGUNA (sementara, sampai backend / database jadi)
|--------------------------------------------------------------------------
| Dipakai halaman Admin > Kelola Pengguna lewat config('demo_users.list').
|
| id         : nomor urut
| name       : nama lengkap
| email      : email untuk login (unik)
| role       : kasir | ic | manager | dc | admin  (key di config/roles.php)
| store      : id store di config('demo.stores'). Wajib untuk kasir, ic, dan
|              manager (terikat ke 1 store). null untuk dc dan admin, karena
|              keduanya melihat semua store.
| status     : aktif | nonaktif
| last_login : teks waktu login terakhir ('-' kalau belum pernah login)
|
| Password tidak disimpan di data demo. Di backend, simpan sebagai hash.
| Aturan: 1 store hanya boleh punya 1 Manager aktif.
*/

return [

    'list' => [

        ['id' => 1,  'name' => 'Admin Sistem',     'email' => 'admin@kopsync.id',           'role' => 'admin',   'store' => null,       'status' => 'aktif',    'last_login' => '14 Okt 2026 · 15:02'],

        ['id' => 2,  'name' => 'Budi Santoso',     'email' => 'budi.dc@kopsync.id',         'role' => 'dc',      'store' => null,       'status' => 'aktif',    'last_login' => '14 Okt 2026 · 11:40'],
        ['id' => 3,  'name' => 'Sari Wulandari',   'email' => 'sari.dc@kopsync.id',         'role' => 'dc',      'store' => null,       'status' => 'aktif',    'last_login' => '13 Okt 2026 · 16:15'],

        ['id' => 4,  'name' => 'Dewi Lestari',     'email' => 'dewi.malang1@kopsync.id',    'role' => 'manager', 'store' => 'malang-1', 'status' => 'aktif',    'last_login' => '14 Okt 2026 · 14:30'],
        ['id' => 5,  'name' => 'Agus Prasetyo',    'email' => 'agus.malang2@kopsync.id',    'role' => 'manager', 'store' => 'malang-2', 'status' => 'aktif',    'last_login' => '14 Okt 2026 · 10:12'],
        ['id' => 6,  'name' => 'Rudi Hartono',     'email' => 'rudi.malang3@kopsync.id',    'role' => 'manager', 'store' => 'malang-3', 'status' => 'aktif',    'last_login' => '13 Okt 2026 · 19:48'],

        ['id' => 7,  'name' => 'Nia Ramadhani',    'email' => 'nia.ic1@kopsync.id',         'role' => 'ic',      'store' => 'malang-1', 'status' => 'aktif',    'last_login' => '14 Okt 2026 · 14:22'],
        ['id' => 8,  'name' => 'Fajar Nugroho',    'email' => 'fajar.ic2@kopsync.id',       'role' => 'ic',      'store' => 'malang-2', 'status' => 'aktif',    'last_login' => '14 Okt 2026 · 09:05'],
        ['id' => 9,  'name' => 'Putri Anggraini',  'email' => 'putri.ic3@kopsync.id',       'role' => 'ic',      'store' => 'malang-3', 'status' => 'aktif',    'last_login' => '12 Okt 2026 · 20:30'],

        ['id' => 10, 'name' => 'Andi Wijaya',      'email' => 'andi.kasir1@kopsync.id',     'role' => 'kasir',   'store' => 'malang-1', 'status' => 'aktif',    'last_login' => '14 Okt 2026 · 14:20'],
        ['id' => 11, 'name' => 'Maya Sari',        'email' => 'maya.kasir1@kopsync.id',     'role' => 'kasir',   'store' => 'malang-1', 'status' => 'aktif',    'last_login' => '13 Okt 2026 · 20:10'],
        ['id' => 12, 'name' => 'Doni Saputra',     'email' => 'doni.kasir2@kopsync.id',     'role' => 'kasir',   'store' => 'malang-2', 'status' => 'aktif',    'last_login' => '14 Okt 2026 · 14:00'],
        ['id' => 13, 'name' => 'Lia Permata',      'email' => 'lia.kasir2@kopsync.id',      'role' => 'kasir',   'store' => 'malang-2', 'status' => 'nonaktif', 'last_login' => '2 Okt 2026 · 17:45'],
        ['id' => 14, 'name' => 'Eko Susanto',      'email' => 'eko.kasir3@kopsync.id',      'role' => 'kasir',   'store' => 'malang-3', 'status' => 'aktif',    'last_login' => '14 Okt 2026 · 14:15'],

    ],

];
