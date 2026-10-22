<?php

namespace App\Http\Controllers\Admin;

use App\Models\KategoriMenu;
use App\Models\MenuProduk;

class MenuProdukController extends MasterDataController
{
    protected string $modelClass = MenuProduk::class;

    protected string $title = 'Menu Produk';

    protected string $routeName = 'admin.menu-produk';

    protected array $fields = [
        [
            'name' => 'kategori_menu_id',
            'label' => 'Kategori menu',
            'type' => 'select',
            'required' => true,
            'optionsModel' => KategoriMenu::class,
            'optionValue' => 'kategori_menu_id',
            'optionLabel' => 'nama_kategori',
            'exists' => ['table' => 'kategori_menu', 'column' => 'kategori_menu_id'],
        ],
        ['name' => 'nama_menu', 'label' => 'Nama menu', 'required' => true, 'max' => 150],
        ['name' => 'harga_jual', 'label' => 'Harga jual', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '0.01'],
        ['name' => 'emoji', 'label' => 'Emoji', 'required' => false, 'max' => 16],
    ];

    protected array $columns = [
        ['label' => 'Menu', 'field' => 'nama_menu'],
        ['label' => 'Kategori', 'field' => 'kategori.nama_kategori'],
        ['label' => 'Harga jual', 'field' => 'harga_jual', 'format' => 'currency'],
        ['label' => 'Varian', 'field' => 'varian_count'],
    ];

    protected array $searchable = ['nama_menu'];

    protected array $filters = [
        'kategori_menu_id' => [
            'label' => 'Kategori',
            'column' => 'kategori_menu_id',
            'model' => KategoriMenu::class,
            'optionValue' => 'kategori_menu_id',
            'optionLabel' => 'nama_kategori',
        ],
    ];

    protected array $sortable = ['nama_menu', 'harga_jual'];

    protected array $with = ['kategori'];

    protected array $withCount = ['varian'];

    protected array $dependents = ['varian'];
}
