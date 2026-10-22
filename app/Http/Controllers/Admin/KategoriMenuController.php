<?php

namespace App\Http\Controllers\Admin;

use App\Models\KategoriMenu;

class KategoriMenuController extends MasterDataController
{
    protected string $modelClass = KategoriMenu::class;

    protected string $title = 'Kategori Menu';

    protected string $routeName = 'admin.kategori-menu';

    protected array $fields = [
        ['name' => 'nama_kategori', 'label' => 'Nama kategori', 'required' => true, 'max' => 100, 'unique' => true],
    ];

    protected array $columns = [
        ['label' => 'Nama kategori', 'field' => 'nama_kategori'],
        ['label' => 'Jumlah menu', 'field' => 'menu_produk_count'],
    ];

    protected array $searchable = ['nama_kategori'];

    protected array $sortable = ['nama_kategori'];

    protected array $withCount = ['menuProduk'];

    protected array $dependents = ['menuProduk'];
}
