<?php

namespace App\Http\Controllers\Admin;

use App\Models\KategoriMenu;

class KategoriMenuController extends MasterDataController
{
    protected string $modelClass = KategoriMenu::class;

    protected string $title = 'Kategori Menu';

    protected string $description = 'Kelompokkan menu produk agar mudah ditemukan di aplikasi kasir POS.';

    protected string $routeName = 'admin.kategori-menu';

    protected array $fields = [
        [
            'name' => 'nama_kategori',
            'label' => 'Nama kategori',
            'required' => true,
            'max' => 100,
            'uniqueCaseInsensitive' => true,
            'placeholder' => 'Contoh: Kopi, Non-Kopi, Makanan Ringan',
        ],
    ];

    protected array $columns = [
        ['label' => 'Nama Kategori', 'field' => 'nama_kategori', 'sort' => 'nama_kategori'],
        [
            'label' => 'Jumlah Menu',
            'field' => 'menu_produk_count',
            'format' => 'count',
            'align' => 'right',
            'linkRoute' => 'admin.menu-produk.index',
            'linkParam' => 'kategori_menu_id',
        ],
    ];

    protected array $searchable = ['nama_kategori'];

    protected array $sortable = ['nama_kategori'];

    protected array $withCount = ['menuProduk'];

    protected array $dependents = ['menuProduk'];

    protected array $dependentLabels = [
        'menuProduk' => 'menu produk',
    ];
}
