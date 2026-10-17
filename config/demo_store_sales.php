<?php

/*
|--------------------------------------------------------------------------
| DATA DUMMY PENJUALAN STORE 2 & STORE 3 (sementara, sampai backend jadi)
|--------------------------------------------------------------------------
| Dipakai halaman Admin > Laporan Seluruh Cabang.
|
| Penjualan dan waste Malang · Store 1 sudah ada di config/demo.php
| ('mutations') dan config/demo_transactions.php. Store 2 dan Store 3 belum
| punya data penjualan, jadi dibuatkan dummy di sini supaya laporan 3 store
| bisa dibandingkan. Hapus file ini kalau data aslinya sudah ada.
|
| transactions : format sama dengan config/demo_transactions.php, ditambah
|                'store' (id store di config('demo.stores')).
|                'menu' = key di config('demo.menus'); total, HPP, dan bahan
|                keluar dihitung dari harga menu dan resep (SOP).
| waste        : format sama dengan mutasi waste di config('demo.mutations'),
|                ditambah 'store'. amount = satuan terkecil (negatif).
|
| Stok masuk semua store diambil dari Surat Jalan 'diterima' di
| config('demo.shipments'), jadi tidak perlu dibuat ulang di sini.
*/

return [

    'transactions' => [

        // ================= MALANG · STORE 2 =================

        ['no' => '#TRX-2101', 'store' => 'malang-2', 'date' => '2026-10-12', 'time' => '10:15', 'cashier' => 'Kasir 02', 'method' => 'qris',  'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'americano', 'qty' => 2], ['menu' => 'croissant', 'qty' => 1]]],
        ['no' => '#TRX-2102', 'store' => 'malang-2', 'date' => '2026-10-12', 'time' => '12:40', 'cashier' => 'Kasir 02', 'method' => 'tunai', 'cash' => 50000, 'status' => 'selesai',
            'items' => [['menu' => 'caffe-latte', 'qty' => 1], ['menu' => 'roti-bakar-keju', 'qty' => 1]]],
        ['no' => '#TRX-2103', 'store' => 'malang-2', 'date' => '2026-10-12', 'time' => '15:20', 'cashier' => 'Kasir 02', 'method' => 'debit', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'vanilla-latte', 'qty' => 2]]],
        ['no' => '#TRX-2104', 'store' => 'malang-2', 'date' => '2026-10-12', 'time' => '18:05', 'cashier' => 'Kasir 02', 'method' => 'qris',  'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'mocha', 'qty' => 1], ['menu' => 'french-fries', 'qty' => 1]]],

        ['no' => '#TRX-2105', 'store' => 'malang-2', 'date' => '2026-10-13', 'time' => '09:30', 'cashier' => 'Kasir 02', 'method' => 'tunai', 'cash' => 50000, 'status' => 'selesai',
            'items' => [['menu' => 'espresso', 'qty' => 1], ['menu' => 'americano', 'qty' => 1]]],
        ['no' => '#TRX-2106', 'store' => 'malang-2', 'date' => '2026-10-13', 'time' => '11:50', 'cashier' => 'Kasir 02', 'method' => 'qris',  'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'nasi-goreng-kampung', 'qty' => 2]]],
        ['no' => '#TRX-2107', 'store' => 'malang-2', 'date' => '2026-10-13', 'time' => '14:10', 'cashier' => 'Kasir 02', 'method' => 'debit', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'cappuccino', 'qty' => 2], ['menu' => 'croissant', 'qty' => 2]]],
        ['no' => '#TRX-2108', 'store' => 'malang-2', 'date' => '2026-10-13', 'time' => '17:45', 'cashier' => 'Kasir 02', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'es-kopi-susu', 'qty' => 3]]],
        ['no' => '#TRX-2109', 'store' => 'malang-2', 'date' => '2026-10-13', 'time' => '19:20', 'cashier' => 'Kasir 02', 'method' => 'qris',  'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'matcha', 'qty' => 1], ['menu' => 'chocolate', 'qty' => 1]]],

        ['no' => '#TRX-2110', 'store' => 'malang-2', 'date' => '2026-10-14', 'time' => '10:05', 'cashier' => 'Kasir 02', 'method' => 'qris',  'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'caffe-latte', 'qty' => 2]]],
        ['no' => '#TRX-2111', 'store' => 'malang-2', 'date' => '2026-10-14', 'time' => '12:25', 'cashier' => 'Kasir 02', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'chicken-katsu-rice', 'qty' => 1], ['menu' => 'americano', 'qty' => 1]]],
        ['no' => '#TRX-2112', 'store' => 'malang-2', 'date' => '2026-10-14', 'time' => '13:15', 'cashier' => 'Kasir 02', 'method' => 'debit', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'hazelnut-latte', 'qty' => 1], ['menu' => 'french-fries', 'qty' => 2]]],
        ['no' => '#TRX-2113', 'store' => 'malang-2', 'date' => '2026-10-14', 'time' => '14:00', 'cashier' => 'Kasir 02', 'method' => 'qris',  'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'mie-goreng', 'qty' => 2]]],


        // ================= MALANG · STORE 3 =================

        ['no' => '#TRX-3101', 'store' => 'malang-3', 'date' => '2026-10-12', 'time' => '11:00', 'cashier' => 'Kasir 03', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'americano', 'qty' => 3]]],
        ['no' => '#TRX-3102', 'store' => 'malang-3', 'date' => '2026-10-12', 'time' => '14:30', 'cashier' => 'Kasir 03', 'method' => 'qris',  'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'es-kopi-susu', 'qty' => 2], ['menu' => 'roti-bakar-keju', 'qty' => 1]]],
        ['no' => '#TRX-3103', 'store' => 'malang-3', 'date' => '2026-10-12', 'time' => '17:10', 'cashier' => 'Kasir 03', 'method' => 'debit', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'chocolate', 'qty' => 2]]],

        ['no' => '#TRX-3104', 'store' => 'malang-3', 'date' => '2026-10-13', 'time' => '10:20', 'cashier' => 'Kasir 03', 'method' => 'qris',  'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'cappuccino', 'qty' => 1], ['menu' => 'croissant', 'qty' => 1]]],
        ['no' => '#TRX-3105', 'store' => 'malang-3', 'date' => '2026-10-13', 'time' => '12:35', 'cashier' => 'Kasir 03', 'method' => 'tunai', 'cash' => 50000, 'status' => 'selesai',
            'items' => [['menu' => 'mie-goreng', 'qty' => 1], ['menu' => 'es-kopi-susu', 'qty' => 1]]],
        ['no' => '#TRX-3106', 'store' => 'malang-3', 'date' => '2026-10-13', 'time' => '16:15', 'cashier' => 'Kasir 03', 'method' => 'debit', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'espresso', 'qty' => 2]]],
        ['no' => '#TRX-3107', 'store' => 'malang-3', 'date' => '2026-10-13', 'time' => '19:05', 'cashier' => 'Kasir 03', 'method' => 'qris',  'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'caramel-macchiato', 'qty' => 2]]],

        ['no' => '#TRX-3108', 'store' => 'malang-3', 'date' => '2026-10-14', 'time' => '09:45', 'cashier' => 'Kasir 03', 'method' => 'tunai', 'cash' => 50000, 'status' => 'selesai',
            'items' => [['menu' => 'americano', 'qty' => 2]]],
        ['no' => '#TRX-3109', 'store' => 'malang-3', 'date' => '2026-10-14', 'time' => '13:30', 'cashier' => 'Kasir 03', 'method' => 'qris',  'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'nasi-goreng-kampung', 'qty' => 1], ['menu' => 'caffe-latte', 'qty' => 1]]],
        ['no' => '#TRX-3110', 'store' => 'malang-3', 'date' => '2026-10-14', 'time' => '14:15', 'cashier' => 'Kasir 03', 'method' => 'debit', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'oat-milk-latte', 'qty' => 1], ['menu' => 'pisang-goreng-keju', 'qty' => 1]]],

    ],


    'waste' => [

        [
            'id' => 'w2-1', 'store' => 'malang-2', 'date' => '2026-10-13', 'time' => '08:30', 'type' => 'waste',
            'ref' => 'Waste · Kedaluwarsa',
            'lines' => [
                ['item' => 'fresh-milk', 'amount' => -1400],
            ],
        ],

        [
            'id' => 'w2-2', 'store' => 'malang-2', 'date' => '2026-10-14', 'time' => '11:40', 'type' => 'waste',
            'ref' => 'Waste · Salah Buat',
            'lines' => [
                ['item' => 'arabica-beans', 'amount' => -18],
                ['item' => 'bubuk-cokelat', 'amount' => -20],
                ['item' => 'fresh-milk', 'amount' => -150],
            ],
        ],

        [
            'id' => 'w3-1', 'store' => 'malang-3', 'date' => '2026-10-12', 'time' => '15:00', 'type' => 'waste',
            'ref' => 'Waste · Tumpah',
            'lines' => [
                ['item' => 'sirup-caramel', 'amount' => -300],
            ],
        ],

        [
            'id' => 'w3-2', 'store' => 'malang-3', 'date' => '2026-10-14', 'time' => '10:10', 'type' => 'waste',
            'ref' => 'Waste · Rusak / Pecah',
            'lines' => [
                ['item' => 'cup-16oz', 'amount' => -20],
            ],
        ],

    ],

];