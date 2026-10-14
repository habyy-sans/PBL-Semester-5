<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data role
    |--------------------------------------------------------------------------
    | name / branch : tampil di sidebar dan topbar (DATA DEMO, nanti dari akun login)
    | home          : nama route halaman awal setelah login
    */

    'roles' => [

        'kasir' => [
            'label'  => 'Kasir',
            'name'   => 'Kasir 01',
            'branch' => 'Cabang Malang',
            'home'   => 'kasir.pos',
        ],

        'ic' => [
            'label'  => 'Kepala Shift / IC',
            'name'   => 'Kepala Shift',
            'branch' => 'Cabang Malang',
            'home'   => 'ic.stock',
        ],

        'manager' => [
            'label'  => 'Manajer Cabang',
            'name'   => 'Manager Cabang',
            'branch' => 'Cabang Malang',
            'home'   => 'manager.dashboard',
        ],

        'dc' => [
            'label'  => 'DC Eksternal',
            'name'   => 'Staf DC',
            'branch' => 'Distribution Center',
            'home'   => 'dc.stock',
        ],

        'admin' => [
            'label'  => 'Admin Sistem',
            'name'   => 'Admin Sistem',
            'branch' => 'Seluruh Cabang',
            'home'   => 'admin.user',
        ],

    ],


    /*
    |--------------------------------------------------------------------------
    | Menu sidebar per role (sesuai MindMeister)
    |--------------------------------------------------------------------------
    */

    'menus' => [

        'kasir' => [
            'MAIN MENU' => [
                ['route' => 'kasir.pos',     'icon' => '▣', 'label' => 'Kasir'],
                ['route' => 'kasir.history', 'icon' => '▤', 'label' => 'Riwayat Transaksi'],
            ],
        ],

        'ic' => [
            'MAIN MENU' => [
                ['route' => 'ic.stock',     'icon' => '▤', 'label' => 'Stok Store'],
                ['route' => 'ic.receiving', 'icon' => '▱', 'label' => 'Penerimaan Barang'],
                ['route' => 'ic.waste',     'icon' => '△', 'label' => 'Waste Log'],
            ],
            'MONITORING' => [
                ['route' => 'ic.mutation', 'icon' => '▥', 'label' => 'Histori Mutasi Stok'],
            ],
        ],

        'manager' => [
            'MAIN MENU' => [
                ['route' => 'manager.dashboard', 'icon' => '⌂', 'label' => 'Dashboard'],
                ['route' => 'manager.alert',     'icon' => '△', 'label' => 'Alert', 'badge' => 5, 'danger' => true],
                ['route' => 'manager.restock',   'icon' => '↻', 'label' => 'Restock', 'badge' => 3],
            ],
            'MONITORING' => [
                ['route' => 'manager.report', 'icon' => '▥', 'label' => 'Laporan Cabang'],
            ],
        ],

        'dc' => [
            'MAIN MENU' => [
                ['route' => 'dc.stock',    'icon' => '▤', 'label' => 'Stok Cabang'],
                ['route' => 'dc.order',    'icon' => '↻', 'label' => 'Pesanan Masuk'],
                ['route' => 'dc.shipment', 'icon' => '▱', 'label' => 'Pengiriman & Surat Jalan'],
            ],
        ],

        'admin' => [
            'MONITORING' => [
                ['route' => 'admin.report', 'icon' => '▥', 'label' => 'Laporan Seluruh Cabang'],
            ],
            'SYSTEM' => [
                ['route' => 'admin.user',    'icon' => '▣', 'label' => 'Kelola Pengguna'],
                ['route' => 'admin.role',    'icon' => '⚙', 'label' => 'Role & Hak Akses'],
                ['route' => 'admin.product', 'icon' => '▤', 'label' => 'Produk & Kategori'],
                ['route' => 'admin.price',   'icon' => '↻', 'label' => 'Kelola Harga'],
            ],
        ],

    ],

];