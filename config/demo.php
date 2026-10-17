<?php

/*
|--------------------------------------------------------------------------
| DATA DEMO (sementara, sampai backend / database jadi)
|--------------------------------------------------------------------------
| Dibaca di halaman lewat config('demo.items') dan config('demo.menus').
| Nanti file ini diganti data dari database tanpa mengubah tampilan.
|
| items : barang / bahan di stok cabang
|   pack  : nama kemasan (Botol, Kantong, Pack, Pcs ...)
|   size  : isi tiap kemasan dalam satuan terkecil (700 = 700 ml per botol)
|   unit  : satuan terkecil (ml / gr / Pcs)
|   (stok tiap store ada di 'stocks', dalam satuan terkecil)
|           contoh: 3430 ml dengan isi 700 ml  =>  tampil "4 Botol 630 ml"
|   min   : batas minimum stok, dalam satuan terkecil
|   price : harga per 1 kemasan (Rp)
|
| default_store : store yang dipakai akun demo Kasir / IC / Manager
|         (nanti dari akun yang login).
| stores : daftar store. Tiap cabang maksimal 3 store; Manager, IC, dan Kasir
|         terikat ke 1 store. DC dan Admin melihat semua store.
| stocks : stok tiap store per barang, dalam satuan terkecil.
|         Struktur: stocks[id store][id barang].
| dc_stock : stok Distribution Center per barang, dalam KEMASAN
|         (DC bekerja dengan kemasan utuh).
| orders : pesanan restock yang sudah disetujui Manager, masuk antrean DC.
|         status: baru | dikirim. Pesanan 'dikirim' memakai item dari
|         Surat Jalan di 'shipment'; pesanan 'baru' memakai 'items'.
|
| shipments : Surat Jalan dari DC ke store ('store'), asal pesanan di 'order'.
|         DC mengirim KEMASAN UTUH
|         (Botol, Kantong, Pack, Pcs), jadi 'sent' dan 'received' dihitung
|         dalam kemasan (bilangan bulat), bukan ml / gr. Saat IC mengonfirmasi,
|         stok bertambah received x isi kemasan (satuan terkecil).
|         status: dikirim | menunggu | diterima
|
| mutations : histori mutasi stok cabang (halaman IC, hanya baca).
|         type: masuk (penerimaan) | keluar (penjualan) | waste
|         lines.amount: perubahan stok dalam SATUAN TERKECIL
|         (+ bertambah, - berkurang). Penjualan digabung per menu per hari:
|         ref berisi total porsi, amount berisi total bahan hari itu.
|
| menus : menu kasir beserta resep (SOP). Setiap menu terjual, bahan di
|         'recipe' berkurang sesuai jumlahnya. Key recipe = key di 'items',
|         jumlah dalam satuan terkecil bahan itu.
*/

return [

    'items' => [

        'arabica-beans' => [
            'name' => 'Arabica Beans', 'category' => 'Bahan Baku',
            'pack' => 'Kantong', 'size' => 1000, 'unit' => 'gr',
            'min' => 10000, 'price' => 180000,
        ],

        'robusta-beans' => [
            'name' => 'Robusta Beans', 'category' => 'Bahan Baku',
            'pack' => 'Kantong', 'size' => 1000, 'unit' => 'gr',
            'min' => 8000, 'price' => 120000,
        ],

        'gula-cair' => [
            'name' => 'Gula Cair', 'category' => 'Bahan Baku',
            'pack' => 'Botol', 'size' => 1000, 'unit' => 'ml',
            'min' => 5000, 'price' => 45000,
        ],

        'bubuk-matcha' => [
            'name' => 'Bubuk Matcha', 'category' => 'Bahan Baku',
            'pack' => 'Pack', 'size' => 500, 'unit' => 'gr',
            'min' => 1000, 'price' => 250000,
        ],

        'bubuk-cokelat' => [
            'name' => 'Bubuk Cokelat', 'category' => 'Bahan Baku',
            'pack' => 'Pack', 'size' => 1000, 'unit' => 'gr',
            'min' => 4000, 'price' => 140000,
        ],

        'fresh-milk' => [
            'name' => 'Fresh Milk', 'category' => 'Susu',
            'pack' => 'Botol', 'size' => 700, 'unit' => 'ml',
            'min' => 7000, 'price' => 28000,
        ],

        'oat-milk' => [
            'name' => 'Oat Milk', 'category' => 'Susu',
            'pack' => 'Karton', 'size' => 1000, 'unit' => 'ml',
            'min' => 6000, 'price' => 38000,
        ],

        'sirup-vanilla' => [
            'name' => 'Sirup Vanilla', 'category' => 'Sirup',
            'pack' => 'Botol', 'size' => 750, 'unit' => 'ml',
            'min' => 6000, 'price' => 42500,
        ],

        'sirup-caramel' => [
            'name' => 'Sirup Caramel', 'category' => 'Sirup',
            'pack' => 'Botol', 'size' => 750, 'unit' => 'ml',
            'min' => 4500, 'price' => 42500,
        ],

        'sirup-hazelnut' => [
            'name' => 'Sirup Hazelnut', 'category' => 'Sirup',
            'pack' => 'Botol', 'size' => 750, 'unit' => 'ml',
            'min' => 3750, 'price' => 42500,
        ],

        'cup-16oz' => [
            'name' => 'Cup 16 oz', 'category' => 'Kemasan',
            'pack' => 'Pcs', 'size' => 1, 'unit' => 'Pcs',
            'min' => 200, 'price' => 800,
        ],

        'tutup-cup' => [
            'name' => 'Tutup Cup', 'category' => 'Kemasan',
            'pack' => 'Pcs', 'size' => 1, 'unit' => 'Pcs',
            'min' => 200, 'price' => 300,
        ],

    ],


    'menus' => [

        'americano' => [
            'name' => 'Americano', 'price' => 18000,
            'recipe' => [
                'arabica-beans' => 18,
            ],
        ],

        'caffe-latte' => [
            'name' => 'Caffe Latte', 'price' => 22000,
            'recipe' => [
                'gula-cair'     => 40,
                'fresh-milk'    => 70,
                'arabica-beans' => 10,
            ],
        ],

        'chocolate' => [
            'name' => 'Chocolate', 'price' => 20000,
            'recipe' => [
                'bubuk-cokelat' => 25,
                'fresh-milk'    => 150,
                'gula-cair'     => 20,
            ],
        ],

        'matcha' => [
            'name' => 'Matcha', 'price' => 23000,
            'recipe' => [
                'bubuk-matcha'  => 5,
                'fresh-milk'    => 150,
                'sirup-vanilla' => 10,
            ],
        ],

    ],


    'shipments' => [
        [
            'no' => '#SJ-018',
            'date' => '14 Okt 2026',
            'origin' => 'Distribution Center',
            'store' => 'malang-1',
            'order' => '#RS-024',
            'status' => 'dikirim',
            'items' => [
                ['item' => 'arabica-beans', 'sent' => 5, 'received' => null, 'note' => ''],
                ['item' => 'robusta-beans', 'sent' => 5, 'received' => null, 'note' => ''],
                ['item' => 'gula-cair', 'sent' => 4, 'received' => null, 'note' => ''],
                ['item' => 'bubuk-matcha', 'sent' => 3, 'received' => null, 'note' => ''],
                ['item' => 'bubuk-cokelat', 'sent' => 4, 'received' => null, 'note' => ''],
                ['item' => 'fresh-milk', 'sent' => 12, 'received' => null, 'note' => ''],
                ['item' => 'oat-milk', 'sent' => 6, 'received' => null, 'note' => ''],
                ['item' => 'sirup-vanilla', 'sent' => 4, 'received' => null, 'note' => ''],
                ['item' => 'sirup-caramel', 'sent' => 4, 'received' => null, 'note' => ''],
                ['item' => 'sirup-hazelnut', 'sent' => 4, 'received' => null, 'note' => ''],
                ['item' => 'cup-16oz', 'sent' => 400, 'received' => null, 'note' => ''],
                ['item' => 'tutup-cup', 'sent' => 400, 'received' => null, 'note' => ''],
            ],
        ],
        [
            'no' => '#SJ-017',
            'date' => '13 Okt 2026',
            'origin' => 'Distribution Center',
            'store' => 'malang-3',
            'order' => '#RS-022',
            'status' => 'diterima',
            'items' => [
                ['item' => 'oat-milk', 'sent' => 6, 'received' => 6, 'note' => ''],
                ['item' => 'sirup-caramel', 'sent' => 4, 'received' => 4, 'note' => ''],
            ],
        ],
        [
            'no' => '#SJ-016',
            'date' => '13 Okt 2026',
            'origin' => 'Distribution Center',
            'store' => 'malang-1',
            'order' => '#RS-023',
            'status' => 'menunggu',
            'items' => [
                ['item' => 'fresh-milk', 'sent' => 10, 'received' => null, 'note' => ''],
                ['item' => 'sirup-vanilla', 'sent' => 6, 'received' => null, 'note' => ''],
                ['item' => 'sirup-hazelnut', 'sent' => 6, 'received' => null, 'note' => ''],
                ['item' => 'cup-16oz', 'sent' => 300, 'received' => null, 'note' => ''],
                ['item' => 'tutup-cup', 'sent' => 300, 'received' => null, 'note' => ''],
            ],
        ],
        [
            'no' => '#SJ-015',
            'date' => '11 Okt 2026',
            'origin' => 'Distribution Center',
            'store' => 'malang-1',
            'order' => '#RS-021',
            'status' => 'diterima',
            'items' => [
                ['item' => 'robusta-beans', 'sent' => 10, 'received' => 10, 'note' => ''],
                ['item' => 'bubuk-matcha', 'sent' => 4, 'received' => 4, 'note' => ''],
                ['item' => 'bubuk-cokelat', 'sent' => 6, 'received' => 6, 'note' => ''],
            ],
        ],
        [
            'no' => '#SJ-014',
            'date' => '9 Okt 2026',
            'origin' => 'Distribution Center',
            'store' => 'malang-1',
            'order' => '#RS-020',
            'status' => 'diterima',
            'items' => [
                ['item' => 'fresh-milk', 'sent' => 20, 'received' => 19, 'note' => 'Kemasan bocor saat pengiriman'],
                ['item' => 'gula-cair', 'sent' => 6, 'received' => 6, 'note' => ''],
                ['item' => 'arabica-beans', 'sent' => 10, 'received' => 10, 'note' => ''],
                ['item' => 'sirup-caramel', 'sent' => 4, 'received' => 4, 'note' => ''],
            ],
        ],
        [
            'no' => '#SJ-013',
            'date' => '7 Okt 2026',
            'origin' => 'Distribution Center',
            'store' => 'malang-2',
            'order' => '#RS-019',
            'status' => 'diterima',
            'items' => [
                ['item' => 'fresh-milk', 'sent' => 10, 'received' => 10, 'note' => ''],
                ['item' => 'cup-16oz', 'sent' => 200, 'received' => 200, 'note' => ''],
            ],
        ],
        [
            'no' => '#SJ-012',
            'date' => '6 Okt 2026',
            'origin' => 'Distribution Center',
            'store' => 'malang-1',
            'order' => '#RS-018',
            'status' => 'diterima',
            'items' => [
                ['item' => 'cup-16oz', 'sent' => 200, 'received' => 200, 'note' => ''],
                ['item' => 'tutup-cup', 'sent' => 200, 'received' => 200, 'note' => ''],
            ],
        ],
    ],


    'mutations' => [
        [
            'id' => 1, 'date' => '2026-10-14', 'time' => '14:20', 'type' => 'keluar',
            'ref' => 'Penjualan · Caffe Latte × 7',
            'lines' => [
                ['item' => 'gula-cair', 'amount' => -280],
                ['item' => 'fresh-milk', 'amount' => -490],
                ['item' => 'arabica-beans', 'amount' => -70],
            ],
        ],

        [
            'id' => 2, 'date' => '2026-10-14', 'time' => '14:05', 'type' => 'keluar',
            'ref' => 'Penjualan · Americano × 9',
            'lines' => [
                ['item' => 'arabica-beans', 'amount' => -162],
            ],
        ],

        [
            'id' => 3, 'date' => '2026-10-14', 'time' => '13:40', 'type' => 'keluar',
            'ref' => 'Penjualan · Matcha × 3',
            'lines' => [
                ['item' => 'bubuk-matcha', 'amount' => -15],
                ['item' => 'fresh-milk', 'amount' => -450],
                ['item' => 'sirup-vanilla', 'amount' => -30],
            ],
        ],

        [
            'id' => 4, 'date' => '2026-10-14', 'time' => '12:55', 'type' => 'keluar',
            'ref' => 'Penjualan · Chocolate × 4',
            'lines' => [
                ['item' => 'bubuk-cokelat', 'amount' => -100],
                ['item' => 'fresh-milk', 'amount' => -600],
                ['item' => 'gula-cair', 'amount' => -80],
            ],
        ],

        [
            'id' => 5, 'date' => '2026-10-14', 'time' => '09:15', 'type' => 'waste',
            'ref' => 'Waste · Rusak / Pecah',
            'lines' => [
                ['item' => 'fresh-milk', 'amount' => -700],
            ],
        ],

        [
            'id' => 6, 'date' => '2026-10-13', 'time' => '20:10', 'type' => 'keluar',
            'ref' => 'Penjualan · Caffe Latte × 12',
            'lines' => [
                ['item' => 'gula-cair', 'amount' => -480],
                ['item' => 'fresh-milk', 'amount' => -840],
                ['item' => 'arabica-beans', 'amount' => -120],
            ],
        ],

        [
            'id' => 7, 'date' => '2026-10-13', 'time' => '19:30', 'type' => 'keluar',
            'ref' => 'Penjualan · Americano × 15',
            'lines' => [
                ['item' => 'arabica-beans', 'amount' => -270],
            ],
        ],

        [
            'id' => 8, 'date' => '2026-10-13', 'time' => '18:45', 'type' => 'keluar',
            'ref' => 'Penjualan · Matcha × 6',
            'lines' => [
                ['item' => 'bubuk-matcha', 'amount' => -30],
                ['item' => 'fresh-milk', 'amount' => -900],
                ['item' => 'sirup-vanilla', 'amount' => -60],
            ],
        ],

        [
            'id' => 9, 'date' => '2026-10-13', 'time' => '17:20', 'type' => 'keluar',
            'ref' => 'Penjualan · Chocolate × 8',
            'lines' => [
                ['item' => 'bubuk-cokelat', 'amount' => -200],
                ['item' => 'fresh-milk', 'amount' => -1200],
                ['item' => 'gula-cair', 'amount' => -160],
            ],
        ],

        [
            'id' => 10, 'date' => '2026-10-13', 'time' => '16:40', 'type' => 'waste',
            'ref' => 'Waste · Salah Buat · Matcha × 1',
            'lines' => [
                ['item' => 'bubuk-matcha', 'amount' => -5],
                ['item' => 'fresh-milk', 'amount' => -150],
                ['item' => 'sirup-vanilla', 'amount' => -10],
            ],
        ],

        [
            'id' => 11, 'date' => '2026-10-12', 'time' => '20:00', 'type' => 'keluar',
            'ref' => 'Penjualan · Caffe Latte × 10',
            'lines' => [
                ['item' => 'gula-cair', 'amount' => -400],
                ['item' => 'fresh-milk', 'amount' => -700],
                ['item' => 'arabica-beans', 'amount' => -100],
            ],
        ],

        [
            'id' => 12, 'date' => '2026-10-12', 'time' => '19:15', 'type' => 'keluar',
            'ref' => 'Penjualan · Americano × 11',
            'lines' => [
                ['item' => 'arabica-beans', 'amount' => -198],
            ],
        ],

        [
            'id' => 13, 'date' => '2026-10-12', 'time' => '18:10', 'type' => 'keluar',
            'ref' => 'Penjualan · Matcha × 4',
            'lines' => [
                ['item' => 'bubuk-matcha', 'amount' => -20],
                ['item' => 'fresh-milk', 'amount' => -600],
                ['item' => 'sirup-vanilla', 'amount' => -40],
            ],
        ],

        [
            'id' => 14, 'date' => '2026-10-12', 'time' => '16:40', 'type' => 'keluar',
            'ref' => 'Penjualan · Chocolate × 5',
            'lines' => [
                ['item' => 'bubuk-cokelat', 'amount' => -125],
                ['item' => 'fresh-milk', 'amount' => -750],
                ['item' => 'gula-cair', 'amount' => -100],
            ],
        ],

        [
            'id' => 15, 'date' => '2026-10-12', 'time' => '11:20', 'type' => 'waste',
            'ref' => 'Waste · Tumpah',
            'lines' => [
                ['item' => 'sirup-vanilla', 'amount' => -150],
            ],
        ],

        [
            'id' => 16, 'date' => '2026-10-11', 'time' => '20:05', 'type' => 'keluar',
            'ref' => 'Penjualan · Caffe Latte × 9',
            'lines' => [
                ['item' => 'gula-cair', 'amount' => -360],
                ['item' => 'fresh-milk', 'amount' => -630],
                ['item' => 'arabica-beans', 'amount' => -90],
            ],
        ],

        [
            'id' => 17, 'date' => '2026-10-11', 'time' => '19:40', 'type' => 'keluar',
            'ref' => 'Penjualan · Americano × 13',
            'lines' => [
                ['item' => 'arabica-beans', 'amount' => -234],
            ],
        ],

        [
            'id' => 18, 'date' => '2026-10-11', 'time' => '17:55', 'type' => 'keluar',
            'ref' => 'Penjualan · Chocolate × 6',
            'lines' => [
                ['item' => 'bubuk-cokelat', 'amount' => -150],
                ['item' => 'fresh-milk', 'amount' => -900],
                ['item' => 'gula-cair', 'amount' => -120],
            ],
        ],

        [
            'id' => 19, 'date' => '2026-10-11', 'time' => '10:30', 'type' => 'masuk',
            'ref' => 'Penerimaan · #SJ-015',
            'lines' => [
                ['item' => 'robusta-beans', 'amount' => 10000],
                ['item' => 'bubuk-matcha', 'amount' => 2000],
                ['item' => 'bubuk-cokelat', 'amount' => 6000],
            ],
        ],

        [
            'id' => 20, 'date' => '2026-10-10', 'time' => '20:15', 'type' => 'keluar',
            'ref' => 'Penjualan · Caffe Latte × 11',
            'lines' => [
                ['item' => 'gula-cair', 'amount' => -440],
                ['item' => 'fresh-milk', 'amount' => -770],
                ['item' => 'arabica-beans', 'amount' => -110],
            ],
        ],

        [
            'id' => 21, 'date' => '2026-10-10', 'time' => '19:20', 'type' => 'keluar',
            'ref' => 'Penjualan · Americano × 14',
            'lines' => [
                ['item' => 'arabica-beans', 'amount' => -252],
            ],
        ],

        [
            'id' => 22, 'date' => '2026-10-10', 'time' => '18:35', 'type' => 'keluar',
            'ref' => 'Penjualan · Matcha × 5',
            'lines' => [
                ['item' => 'bubuk-matcha', 'amount' => -25],
                ['item' => 'fresh-milk', 'amount' => -750],
                ['item' => 'sirup-vanilla', 'amount' => -50],
            ],
        ],

        [
            'id' => 23, 'date' => '2026-10-10', 'time' => '16:10', 'type' => 'keluar',
            'ref' => 'Penjualan · Chocolate × 7',
            'lines' => [
                ['item' => 'bubuk-cokelat', 'amount' => -175],
                ['item' => 'fresh-milk', 'amount' => -1050],
                ['item' => 'gula-cair', 'amount' => -140],
            ],
        ],

        [
            'id' => 24, 'date' => '2026-10-10', 'time' => '14:05', 'type' => 'waste',
            'ref' => 'Waste · Kedaluwarsa',
            'lines' => [
                ['item' => 'fresh-milk', 'amount' => -1400],
            ],
        ],

        [
            'id' => 25, 'date' => '2026-10-09', 'time' => '15:30', 'type' => 'waste',
            'ref' => 'Waste · Salah Buat · Caffe Latte × 2',
            'lines' => [
                ['item' => 'gula-cair', 'amount' => -80],
                ['item' => 'fresh-milk', 'amount' => -140],
                ['item' => 'arabica-beans', 'amount' => -20],
            ],
        ],

        [
            'id' => 26, 'date' => '2026-10-09', 'time' => '09:45', 'type' => 'masuk',
            'ref' => 'Penerimaan · #SJ-014',
            'lines' => [
                ['item' => 'fresh-milk', 'amount' => 13300],
                ['item' => 'gula-cair', 'amount' => 6000],
                ['item' => 'arabica-beans', 'amount' => 10000],
                ['item' => 'sirup-caramel', 'amount' => 3000],
            ],
        ],

        [
            'id' => 27, 'date' => '2026-10-06', 'time' => '11:10', 'type' => 'masuk',
            'ref' => 'Penerimaan · #SJ-012',
            'lines' => [
                ['item' => 'cup-16oz', 'amount' => 200],
                ['item' => 'tutup-cup', 'amount' => 200],
            ],
        ],

    ],

    'default_store' => 'malang-1',

    'stores' => [
        'malang-1' => ['name' => 'Malang · Store 1', 'cabang' => 'Malang'],
        'malang-2' => ['name' => 'Malang · Store 2', 'cabang' => 'Malang'],
        'malang-3' => ['name' => 'Malang · Store 3', 'cabang' => 'Malang'],
    ],

    'stocks' => [
        'malang-1' => ['arabica-beans' => 24000, 'robusta-beans' => 6000, 'gula-cair' => 12600, 'bubuk-matcha' => 0, 'bubuk-cokelat' => 3200, 'fresh-milk' => 3430, 'oat-milk' => 12000, 'sirup-vanilla' => 3150, 'sirup-caramel' => 6600, 'sirup-hazelnut' => 0, 'cup-16oz' => 150, 'tutup-cup' => 180],
        'malang-2' => ['arabica-beans' => 15000, 'robusta-beans' => 9500, 'gula-cair' => 8000, 'bubuk-matcha' => 1500, 'bubuk-cokelat' => 5200, 'fresh-milk' => 2100, 'oat-milk' => 7000, 'sirup-vanilla' => 1500, 'sirup-caramel' => 5200, 'sirup-hazelnut' => 4500, 'cup-16oz' => 80, 'tutup-cup' => 450],
        'malang-3' => ['arabica-beans' => 2000, 'robusta-beans' => 11000, 'gula-cair' => 1500, 'bubuk-matcha' => 0, 'bubuk-cokelat' => 6500, 'fresh-milk' => 8400, 'oat-milk' => 6500, 'sirup-vanilla' => 6800, 'sirup-caramel' => 4800, 'sirup-hazelnut' => 3900, 'cup-16oz' => 520, 'tutup-cup' => 60],
    ],

    'dc_stock' => ['arabica-beans' => 60, 'robusta-beans' => 50, 'gula-cair' => 40, 'bubuk-matcha' => 30, 'bubuk-cokelat' => 35, 'fresh-milk' => 120, 'oat-milk' => 45, 'sirup-vanilla' => 30, 'sirup-caramel' => 28, 'sirup-hazelnut' => 25, 'cup-16oz' => 5000, 'tutup-cup' => 5000],

    'orders' => [
        [
            'no' => '#RS-026',
            'date' => '14 Okt 2026',
            'store' => 'malang-3',
            'status' => 'baru',
            'shipment' => null,
            'items' => [
                ['item' => 'arabica-beans', 'qty' => 4],
                ['item' => 'gula-cair', 'qty' => 3],
                ['item' => 'bubuk-matcha', 'qty' => 2],
                ['item' => 'tutup-cup', 'qty' => 300],
            ],
        ],
        [
            'no' => '#RS-025',
            'date' => '14 Okt 2026',
            'store' => 'malang-2',
            'status' => 'baru',
            'shipment' => null,
            'items' => [
                ['item' => 'fresh-milk', 'qty' => 8],
                ['item' => 'sirup-vanilla', 'qty' => 4],
                ['item' => 'cup-16oz', 'qty' => 300],
            ],
        ],
        [
            'no' => '#RS-024',
            'date' => '13 Okt 2026',
            'store' => 'malang-1',
            'status' => 'dikirim',
            'shipment' => '#SJ-018',
            'items' => [],
        ],
        [
            'no' => '#RS-023',
            'date' => '12 Okt 2026',
            'store' => 'malang-1',
            'status' => 'dikirim',
            'shipment' => '#SJ-016',
            'items' => [],
        ],
        [
            'no' => '#RS-022',
            'date' => '12 Okt 2026',
            'store' => 'malang-3',
            'status' => 'dikirim',
            'shipment' => '#SJ-017',
            'items' => [],
        ],
        [
            'no' => '#RS-021',
            'date' => '10 Okt 2026',
            'store' => 'malang-1',
            'status' => 'dikirim',
            'shipment' => '#SJ-015',
            'items' => [],
        ],
        [
            'no' => '#RS-020',
            'date' => '8 Okt 2026',
            'store' => 'malang-1',
            'status' => 'dikirim',
            'shipment' => '#SJ-014',
            'items' => [],
        ],
        [
            'no' => '#RS-019',
            'date' => '6 Okt 2026',
            'store' => 'malang-2',
            'status' => 'dikirim',
            'shipment' => '#SJ-013',
            'items' => [],
        ],
        [
            'no' => '#RS-018',
            'date' => '5 Okt 2026',
            'store' => 'malang-1',
            'status' => 'dikirim',
            'shipment' => '#SJ-012',
            'items' => [],
        ],
    ],

];