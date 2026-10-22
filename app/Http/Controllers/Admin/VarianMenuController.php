<?php

namespace App\Http\Controllers\Admin;

use App\Models\MenuProduk;
use App\Models\VarianMenu;

class VarianMenuController extends MasterDataController
{
    protected string $modelClass = VarianMenu::class;

    protected string $title = 'Varian Menu';

    protected string $routeName = 'admin.varian-menu';

    protected array $fields = [
        [
            'name' => 'menu_id',
            'label' => 'Menu produk',
            'type' => 'select',
            'required' => true,
            'optionsModel' => MenuProduk::class,
            'optionValue' => 'menu_id',
            'optionLabel' => 'nama_menu',
            'exists' => ['table' => 'menu_produk', 'column' => 'menu_id'],
        ],
        ['name' => 'nama_varian', 'label' => 'Nama varian', 'required' => true, 'max' => 100],
        ['name' => 'harga_tambahan', 'label' => 'Harga tambahan', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '0.01'],
    ];

    protected array $columns = [
        ['label' => 'Menu', 'field' => 'menu.nama_menu'],
        ['label' => 'Varian', 'field' => 'nama_varian'],
        ['label' => 'Harga tambahan', 'field' => 'harga_tambahan', 'format' => 'currency'],
        ['label' => 'Bahan resep', 'field' => 'resep_count'],
    ];

    protected array $searchable = ['nama_varian'];

    protected array $searchRelations = ['menu' => 'nama_menu'];

    protected array $filters = [
        'menu_id' => [
            'label' => 'Menu',
            'column' => 'menu_id',
            'model' => MenuProduk::class,
            'optionValue' => 'menu_id',
            'optionLabel' => 'nama_menu',
        ],
    ];

    protected array $sortable = ['nama_varian', 'harga_tambahan'];

    protected array $with = ['menu'];

    protected array $withCount = ['resep'];

    protected array $dependents = ['resep'];
}
