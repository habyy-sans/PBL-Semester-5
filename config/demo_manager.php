<?php

/*
|--------------------------------------------------------------------------
| DATA DEMO DASHBOARD MANAGER (sementara, sampai backend / database jadi)
|--------------------------------------------------------------------------
| 1 Manager = 1 Store. Dashboard hanya menampilkan data store milik Manager
| yang login, tanpa pilihan store.
|
| branch : key cabang di config('demo.branches')
| store  : urutan store (mulai dari 0) di config('demo.branches.<branch>.stores')
|
| Nanti nama store diambil dari akun login (kolom store_id milik user).
*/

return [

    'branch' => 'malang',

    'store' => 0,   // Store Dinoyo

];
