<?php

/*
|--------------------------------------------------------------------------
| DATA DEMO INSTRUKSI PENGIRIMAN / RESTOCK (sementara, sampai backend jadi)
|--------------------------------------------------------------------------
| Dibaca di halaman Manager > Restock lewat config('demo_restock.orders').
|
| Ini riwayat instruksi yang SUDAH disetujui Manager dan diteruskan ke DC.
| Draft yang masih menunggu approval (#RS-024) tidak ada di sini: draft
| dibuat otomatis oleh halaman dari bahan yang stoknya di bawah minimum
| (jumlah = stok minimum - stok aktual).
|
| no       : nomor Instruksi Pengiriman
| date     : tanggal disetujui (Y-m-d)
| source   : otomatis (dari alert restock) | manual (reorder oleh Manager)
| reason   : alasan, hanya untuk reorder manual
| shipment : nomor Surat Jalan di config('demo.shipments'). Isi barang dan
|            status (dikirim / menunggu konfirmasi IC / diterima) diambil
|            dari Surat Jalan tersebut.
*/

return [

    'orders' => [

        ['no' => '#RS-023', 'date' => '2026-10-13', 'source' => 'otomatis', 'shipment' => '#SJ-018'],

        ['no' => '#RS-022', 'date' => '2026-10-12', 'source' => 'manual', 'reason' => 'Lonjakan pengunjung', 'shipment' => '#SJ-016'],

        ['no' => '#RS-021', 'date' => '2026-10-10', 'source' => 'otomatis', 'shipment' => '#SJ-015'],

        ['no' => '#RS-020', 'date' => '2026-10-08', 'source' => 'otomatis', 'shipment' => '#SJ-014'],

        ['no' => '#RS-019', 'date' => '2026-10-05', 'source' => 'manual', 'reason' => 'Pengganti barang waste', 'shipment' => '#SJ-012'],

    ],

];