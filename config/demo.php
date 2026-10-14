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
|   stock : stok cabang SAAT INI, dalam satuan terkecil
|           contoh: 3430 ml dengan isi 700 ml  =>  tampil "4 Botol 630 ml"
|   min   : batas minimum stok, dalam satuan terkecil
|   price : harga per 1 kemasan (Rp)
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
            'stock' => 24000, 'min' => 10000, 'price' => 180000,
        ],

        'robusta-beans' => [
            'name' => 'Robusta Beans', 'category' => 'Bahan Baku',
            'pack' => 'Kantong', 'size' => 1000, 'unit' => 'gr',
            'stock' => 6000, 'min' => 8000, 'price' => 120000,
        ],

        'gula-cair' => [
            'name' => 'Gula Cair', 'category' => 'Bahan Baku',
            'pack' => 'Botol', 'size' => 1000, 'unit' => 'ml',
            'stock' => 12600, 'min' => 5000, 'price' => 45000,
        ],

        'bubuk-matcha' => [
            'name' => 'Bubuk Matcha', 'category' => 'Bahan Baku',
            'pack' => 'Pack', 'size' => 500, 'unit' => 'gr',
            'stock' => 0, 'min' => 1000, 'price' => 250000,
        ],

        'bubuk-cokelat' => [
            'name' => 'Bubuk Cokelat', 'category' => 'Bahan Baku',
            'pack' => 'Pack', 'size' => 1000, 'unit' => 'gr',
            'stock' => 3200, 'min' => 4000, 'price' => 140000,
        ],

        'fresh-milk' => [
            'name' => 'Fresh Milk', 'category' => 'Susu',
            'pack' => 'Botol', 'size' => 700, 'unit' => 'ml',
            'stock' => 3430, 'min' => 7000, 'price' => 28000,
        ],

        'oat-milk' => [
            'name' => 'Oat Milk', 'category' => 'Susu',
            'pack' => 'Karton', 'size' => 1000, 'unit' => 'ml',
            'stock' => 12000, 'min' => 6000, 'price' => 38000,
        ],

        'sirup-vanilla' => [
            'name' => 'Sirup Vanilla', 'category' => 'Sirup',
            'pack' => 'Botol', 'size' => 750, 'unit' => 'ml',
            'stock' => 3150, 'min' => 6000, 'price' => 42500,
        ],

        'sirup-caramel' => [
            'name' => 'Sirup Caramel', 'category' => 'Sirup',
            'pack' => 'Botol', 'size' => 750, 'unit' => 'ml',
            'stock' => 6600, 'min' => 4500, 'price' => 42500,
        ],

        'sirup-hazelnut' => [
            'name' => 'Sirup Hazelnut', 'category' => 'Sirup',
            'pack' => 'Botol', 'size' => 750, 'unit' => 'ml',
            'stock' => 0, 'min' => 3750, 'price' => 42500,
        ],

        'cup-16oz' => [
            'name' => 'Cup 16 oz', 'category' => 'Kemasan',
            'pack' => 'Pcs', 'size' => 1, 'unit' => 'Pcs',
            'stock' => 150, 'min' => 200, 'price' => 800,
        ],

        'tutup-cup' => [
            'name' => 'Tutup Cup', 'category' => 'Kemasan',
            'pack' => 'Pcs', 'size' => 1, 'unit' => 'Pcs',
            'stock' => 180, 'min' => 200, 'price' => 300,
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

];