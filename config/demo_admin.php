<?php

/*
|--------------------------------------------------------------------------
| DATA DEMO ADMIN (sementara, sampai backend / database jadi)
|--------------------------------------------------------------------------
| Dibaca di halaman Admin lewat config('demo_admin.users') dst.
|
| current_user : id akun Admin yang sedang login (tidak boleh menonaktifkan diri sendiri)
|
| users :
|   role   : kasir | ic | manager | dc | admin   (key di config('menu.roles'))
|   branch : key cabang di config('demo.branches'), null untuk DC dan Admin
|   store  : urutan store (mulai dari 0) di config('demo.branches.<branch>.stores'),
|            null untuk DC dan Admin
|   status : aktif | nonaktif
|   Akun demo login (username = key role, password password123) ada di
|   halaman login: admin, dc, manager, ic, kasir.
|
| locks : aturan hak akses yang TIDAK boleh diubah di halaman Role & Hak Akses.
|   admin_only_prefix : route berawalan ini hanya boleh dimiliki Admin
|   always_on         : role => route yang selalu aktif (agar Admin tidak terkunci)
|   blocked           : role => route yang selalu nonaktif (DC tidak boleh lihat omzet / laba rugi)
*/

return [

    'current_user' => 1,

    'users' => [
        ['id' => 1, 'name' => 'Admin Sistem', 'username' => 'admin', 'email' => 'admin@kopsync.test', 'role' => 'admin', 'branch' => null, 'store' => null, 'status' => 'aktif'],
        ['id' => 2, 'name' => 'Staf DC', 'username' => 'dc', 'email' => 'dc@kopsync.test', 'role' => 'dc', 'branch' => null, 'store' => null, 'status' => 'aktif'],
        ['id' => 3, 'name' => 'Manager Cabang', 'username' => 'manager', 'email' => 'manager@kopsync.test', 'role' => 'manager', 'branch' => 'malang', 'store' => 0, 'status' => 'aktif'],
        ['id' => 4, 'name' => 'Kepala Shift', 'username' => 'ic', 'email' => 'ic@kopsync.test', 'role' => 'ic', 'branch' => 'malang', 'store' => 0, 'status' => 'aktif'],
        ['id' => 5, 'name' => 'Kasir 01', 'username' => 'kasir', 'email' => 'kasir@kopsync.test', 'role' => 'kasir', 'branch' => 'malang', 'store' => 0, 'status' => 'aktif'],
        ['id' => 6, 'name' => 'Rina Wulandari', 'username' => 'rina.wulandari', 'email' => 'rina.w@kopsync.test', 'role' => 'manager', 'branch' => 'malang', 'store' => 1, 'status' => 'aktif'],
        ['id' => 7, 'name' => 'Dedi Prasetyo', 'username' => 'dedi.prasetyo', 'email' => 'dedi.p@kopsync.test', 'role' => 'ic', 'branch' => 'malang', 'store' => 1, 'status' => 'aktif'],
        ['id' => 8, 'name' => 'Kasir 02', 'username' => 'kasir02', 'email' => 'kasir02@kopsync.test', 'role' => 'kasir', 'branch' => 'malang', 'store' => 1, 'status' => 'aktif'],
        ['id' => 9, 'name' => 'Bima Saputra', 'username' => 'bima.saputra', 'email' => 'bima.s@kopsync.test', 'role' => 'manager', 'branch' => 'malang', 'store' => 2, 'status' => 'aktif'],
        ['id' => 10, 'name' => 'Siti Aminah', 'username' => 'siti.aminah', 'email' => 'siti.a@kopsync.test', 'role' => 'ic', 'branch' => 'malang', 'store' => 2, 'status' => 'aktif'],
        ['id' => 11, 'name' => 'Kasir 03', 'username' => 'kasir03', 'email' => 'kasir03@kopsync.test', 'role' => 'kasir', 'branch' => 'malang', 'store' => 2, 'status' => 'nonaktif'],
        ['id' => 12, 'name' => 'Hendra Gunawan', 'username' => 'hendra.gunawan', 'email' => 'hendra.g@kopsync.test', 'role' => 'manager', 'branch' => 'surabaya', 'store' => 0, 'status' => 'aktif'],
        ['id' => 13, 'name' => 'Lina Marlina', 'username' => 'lina.marlina', 'email' => 'lina.m@kopsync.test', 'role' => 'ic', 'branch' => 'surabaya', 'store' => 0, 'status' => 'aktif'],
        ['id' => 14, 'name' => 'Kasir 04', 'username' => 'kasir04', 'email' => 'kasir04@kopsync.test', 'role' => 'kasir', 'branch' => 'surabaya', 'store' => 0, 'status' => 'aktif'],
        ['id' => 15, 'name' => 'Maya Kusuma', 'username' => 'maya.kusuma', 'email' => 'maya.k@kopsync.test', 'role' => 'manager', 'branch' => 'jakarta', 'store' => 0, 'status' => 'aktif'],
        ['id' => 16, 'name' => 'Rizky Ramadhan', 'username' => 'rizky.ramadhan', 'email' => 'rizky.r@kopsync.test', 'role' => 'ic', 'branch' => 'jakarta', 'store' => 0, 'status' => 'aktif'],
        ['id' => 17, 'name' => 'Kasir 05', 'username' => 'kasir05', 'email' => 'kasir05@kopsync.test', 'role' => 'kasir', 'branch' => 'jakarta', 'store' => 0, 'status' => 'aktif'],
    ],

    'locks' => [
        'admin_only_prefix' => 'admin.',
        'always_on' => [
            'admin' => ['admin.user', 'admin.role'],
        ],
        'blocked' => [
            'dc' => ['kasir.history', 'manager.dashboard', 'manager.report'],
        ],
    ],

];