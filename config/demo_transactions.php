<?php

/*
|--------------------------------------------------------------------------
| DATA DEMO RIWAYAT TRANSAKSI (sementara, sampai backend / database jadi)
|--------------------------------------------------------------------------
| Dibaca di halaman lewat config('demo_transactions.list').
|
| no      : nomor transaksi
| date    : tanggal (Y-m-d)         time : jam (H:i)
| cashier : nama kasir
| method  : tunai | qris | debit
| cash    : uang diterima (hanya untuk tunai, selain itu null)
| status  : selesai | batal
| reason  : alasan pembatalan (hanya untuk status batal)
| items   : daftar menu + qty. Key 'menu' = key di config('demo.menus').
|           Total, HPP, dan bahan terpakai dihitung dari menu + resep (SOP).
|
| Jumlah porsi per menu per hari SAMA dengan data 'mutations' di
| config/demo.php (tgl 12, 13, 14 Okt 2026), jadi riwayat transaksi dan
| histori mutasi stok konsisten. Transaksi 'batal' tidak memotong stok.
|
| Catatan backend: harga tiap item sebaiknya disimpan sebagai snapshot saat
| transaksi (supaya perubahan harga menu tidak mengubah riwayat lama).
*/

return [

    'list' => [

        // ================= 14 OKT 2026 =================

        ['no' => '#TRX-0129', 'date' => '2026-10-14', 'time' => '14:20', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'caffe-latte', 'qty' => 3], ['menu' => 'chocolate', 'qty' => 1]]],

        ['no' => '#TRX-0128', 'date' => '2026-10-14', 'time' => '14:05', 'cashier' => 'Kasir 01', 'method' => 'debit', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'americano', 'qty' => 2], ['menu' => 'caffe-latte', 'qty' => 1]]],

        ['no' => '#TRX-0127', 'date' => '2026-10-14', 'time' => '13:40', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'matcha', 'qty' => 2], ['menu' => 'americano', 'qty' => 1]]],

        ['no' => '#TRX-0126', 'date' => '2026-10-14', 'time' => '12:55', 'cashier' => 'Kasir 01', 'method' => 'qris', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'chocolate', 'qty' => 2], ['menu' => 'americano', 'qty' => 1]]],

        ['no' => '#TRX-0125', 'date' => '2026-10-14', 'time' => '12:30', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 70000, 'status' => 'selesai',
            'items' => [['menu' => 'matcha', 'qty' => 1], ['menu' => 'caffe-latte', 'qty' => 2]]],

        ['no' => '#TRX-0124', 'date' => '2026-10-14', 'time' => '11:10', 'cashier' => 'Kasir 01', 'method' => 'debit', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'chocolate', 'qty' => 1], ['menu' => 'americano', 'qty' => 2]]],

        ['no' => '#TRX-0123', 'date' => '2026-10-14', 'time' => '10:25', 'cashier' => 'Kasir 01', 'method' => 'qris', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'caffe-latte', 'qty' => 1], ['menu' => 'americano', 'qty' => 1]]],

        ['no' => '#TRX-0122', 'date' => '2026-10-14', 'time' => '09:40', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 50000, 'status' => 'selesai',
            'items' => [['menu' => 'americano', 'qty' => 2]]],

        // ================= 13 OKT 2026 =================

        ['no' => '#TRX-0121', 'date' => '2026-10-13', 'time' => '20:10', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 110000, 'status' => 'selesai',
            'items' => [['menu' => 'caffe-latte', 'qty' => 3], ['menu' => 'chocolate', 'qty' => 2]]],

        ['no' => '#TRX-0120', 'date' => '2026-10-13', 'time' => '19:30', 'cashier' => 'Kasir 01', 'method' => 'qris', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'americano', 'qty' => 4]]],

        ['no' => '#TRX-0119', 'date' => '2026-10-13', 'time' => '18:45', 'cashier' => 'Kasir 01', 'method' => 'debit', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'matcha', 'qty' => 3], ['menu' => 'caffe-latte', 'qty' => 1]]],

        ['no' => '#TRX-0118', 'date' => '2026-10-13', 'time' => '17:20', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'chocolate', 'qty' => 3], ['menu' => 'americano', 'qty' => 2]]],

        ['no' => '#TRX-0117', 'date' => '2026-10-13', 'time' => '16:00', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'caffe-latte', 'qty' => 3], ['menu' => 'americano', 'qty' => 1]]],

        ['no' => '#TRX-0116', 'date' => '2026-10-13', 'time' => '15:10', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => null, 'status' => 'batal',
            'reason' => 'Pelanggan membatalkan pesanan sebelum diproses',
            'items' => [['menu' => 'chocolate', 'qty' => 1]]],

        ['no' => '#TRX-0115', 'date' => '2026-10-13', 'time' => '14:30', 'cashier' => 'Kasir 01', 'method' => 'qris', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'chocolate', 'qty' => 1], ['menu' => 'matcha', 'qty' => 1]]],

        ['no' => '#TRX-0114', 'date' => '2026-10-13', 'time' => '13:00', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'americano', 'qty' => 2], ['menu' => 'caffe-latte', 'qty' => 2]]],

        ['no' => '#TRX-0113', 'date' => '2026-10-13', 'time' => '12:10', 'cashier' => 'Kasir 01', 'method' => 'debit', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'matcha', 'qty' => 2], ['menu' => 'americano', 'qty' => 2]]],

        ['no' => '#TRX-0112', 'date' => '2026-10-13', 'time' => '11:15', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'chocolate', 'qty' => 2], ['menu' => 'caffe-latte', 'qty' => 1]]],

        ['no' => '#TRX-0111', 'date' => '2026-10-13', 'time' => '10:05', 'cashier' => 'Kasir 01', 'method' => 'qris', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'caffe-latte', 'qty' => 2], ['menu' => 'americano', 'qty' => 1]]],

        ['no' => '#TRX-0110', 'date' => '2026-10-13', 'time' => '09:20', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'americano', 'qty' => 3]]],

        // ================= 12 OKT 2026 =================

        ['no' => '#TRX-0109', 'date' => '2026-10-12', 'time' => '20:00', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'caffe-latte', 'qty' => 3]]],

        ['no' => '#TRX-0108', 'date' => '2026-10-12', 'time' => '19:15', 'cashier' => 'Kasir 01', 'method' => 'qris', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'americano', 'qty' => 4]]],

        ['no' => '#TRX-0107', 'date' => '2026-10-12', 'time' => '18:10', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 150000, 'status' => 'selesai',
            'items' => [['menu' => 'matcha', 'qty' => 3], ['menu' => 'caffe-latte', 'qty' => 2]]],

        ['no' => '#TRX-0106', 'date' => '2026-10-12', 'time' => '16:40', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 60000, 'status' => 'selesai',
            'items' => [['menu' => 'chocolate', 'qty' => 2], ['menu' => 'americano', 'qty' => 1]]],

        ['no' => '#TRX-0105', 'date' => '2026-10-12', 'time' => '14:20', 'cashier' => 'Kasir 01', 'method' => 'debit', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'chocolate', 'qty' => 2], ['menu' => 'caffe-latte', 'qty' => 2]]],

        ['no' => '#TRX-0104', 'date' => '2026-10-12', 'time' => '13:10', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 100000, 'status' => 'selesai',
            'items' => [['menu' => 'americano', 'qty' => 3], ['menu' => 'caffe-latte', 'qty' => 1]]],

        ['no' => '#TRX-0103', 'date' => '2026-10-12', 'time' => '11:45', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 50000, 'status' => 'selesai',
            'items' => [['menu' => 'chocolate', 'qty' => 1], ['menu' => 'matcha', 'qty' => 1]]],

        ['no' => '#TRX-0102', 'date' => '2026-10-12', 'time' => '10:30', 'cashier' => 'Kasir 01', 'method' => 'qris', 'cash' => null, 'status' => 'selesai',
            'items' => [['menu' => 'caffe-latte', 'qty' => 2], ['menu' => 'americano', 'qty' => 1]]],

        ['no' => '#TRX-0101', 'date' => '2026-10-12', 'time' => '09:35', 'cashier' => 'Kasir 01', 'method' => 'tunai', 'cash' => 50000, 'status' => 'selesai',
            'items' => [['menu' => 'americano', 'qty' => 2]]],

    ],

];
