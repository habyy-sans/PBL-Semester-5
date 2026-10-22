<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data role
    |--------------------------------------------------------------------------
    | db    : nilai kolom pengguna.role di database (sumber kebenaran role)
    | home  : nama route halaman awal setelah login
    | label : tampilan role di sidebar / pesan akses
    */

    'roles' => [

        'kasir' => [
            'label' => 'Kasir',
            'db' => 'Kasir',
            'home' => 'kasir.pos',
        ],

        'ic' => [
            'label' => 'Kepala Shift / IC',
            'db' => 'Kepala Shift/Inventory Control (IC)',
            'home' => 'ic.stock',
        ],

        'manager' => [
            'label' => 'Manajer Cabang',
            'db' => 'Manajer Cabang',
            'home' => 'manager.dashboard',
        ],

        'dc' => [
            'label' => 'DC Eksternal',
            'db' => 'DC Eksternal',
            'home' => 'dc.stock',
        ],

        'admin' => [
            'label' => 'Admin Sistem',
            'db' => 'Admin Sistem',
            'home' => 'admin.dashboard',
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
                ['route' => 'dc.stock',    'icon' => '▤', 'label' => 'Stock DC'],
                ['route' => 'dc.order',    'icon' => '↻', 'label' => 'Pesanan Masuk'],
                ['route' => 'dc.shipment', 'icon' => '▱', 'label' => 'Pengiriman & Surat Jalan'],
                ['route' => 'dc.bahan-baku.index', 'icon' => '▦', 'label' => 'Kelola Bahan Baku'],
            ],
        ],

        'admin' => [
            'MONITORING' => [
                ['route' => 'admin.dashboard', 'icon' => '⌂', 'label' => 'Dashboard'],
                ['route' => 'admin.stok-cabang.index', 'icon' => '▤', 'label' => 'Stok Cabang'],
                ['route' => 'admin.stok-dc.index', 'icon' => '▤', 'label' => 'Stok DC'],
            ],
            'SYSTEM' => [
                ['route' => 'admin.pengguna.index', 'icon' => '▣', 'label' => 'Kelola Pengguna'],
                ['route' => 'admin.role',    'icon' => '⚙', 'label' => 'Role & Hak Akses'],
                ['route' => 'admin.cabang.index', 'icon' => '⌂', 'label' => 'Cabang'],
                ['route' => 'admin.dc.index', 'icon' => '▦', 'label' => 'Distribution Center'],
                ['route' => 'admin.bahan-baku.index', 'icon' => '▦', 'label' => 'Bahan Baku'],
                ['route' => 'admin.kategori-menu.index', 'icon' => '▤', 'label' => 'Kategori Menu'],
                ['route' => 'admin.menu-produk.index', 'icon' => '▤', 'label' => 'Menu Produk'],
                ['route' => 'admin.varian-menu.index', 'icon' => '≡', 'label' => 'Varian Menu'],
                ['route' => 'admin.resep-menu.index', 'icon' => '≡', 'label' => 'Resep Menu'],
            ],
        ],

    ],

];
