<?php

namespace App\Http\Controllers\Admin;

use App\Models\MenuProduk;
use App\Models\VarianMenu;

class VarianMenuController extends MasterDataController
{
    protected string $modelClass = VarianMenu::class;

    protected string $title = 'Varian Menu';

    protected string $description = 'Kelola pilihan varian untuk tiap menu produk, tambahan harga, dan keterhubungan resep.';

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
        [
            'name' => 'nama_varian',
            'label' => 'Nama varian',
            'required' => true,
            'max' => 100,
            'placeholder' => 'Contoh: Regular, Large, Iced',
        ],
        [
            'name' => 'harga_tambahan',
            'label' => 'Harga tambahan',
            'type' => 'number',
            'required' => true,
            'min' => 0,
            'max' => '999999999.99',
            'step' => 'any',
            'default' => 0,
            'help' => 'Isi 0 jika harga varian sama dengan harga dasar menu.',
        ],
    ];

    protected array $compositeUnique = [
        [
            'columns' => ['menu_id', 'nama_varian'],
            'caseInsensitive' => ['nama_varian'],
            'errorField' => 'nama_varian',
            'message' => 'Nama varian ini sudah terdaftar pada menu produk yang dipilih.',
        ],
    ];

    protected array $searchable = ['nama_varian'];

    protected array $searchRelations = ['menu' => 'nama_menu'];

    protected array $filters = [
        'menu_id' => [
            'label' => 'Menu produk',
            'column' => 'menu_id',
            'model' => MenuProduk::class,
            'optionValue' => 'menu_id',
            'optionLabel' => 'nama_menu',
        ],
    ];

    protected array $sortable = ['nama_varian', 'harga_tambahan'];

    protected array $with = ['menu'];

    protected array $withCount = ['resep'];

    protected array $dependents = ['resep', 'detailTransaksi'];

    protected array $dependentLabels = [
        'resep' => 'resep menu',
        'detailTransaksi' => 'riwayat transaksi penjualan',
    ];

    public function __construct()
    {
        $this->columns = [
            ['label' => 'Menu Produk', 'field' => 'menu.nama_menu'],
            ['label' => 'Varian', 'field' => 'nama_varian', 'sort' => 'nama_varian'],
            ['label' => 'Harga Tambahan', 'field' => 'harga_tambahan', 'format' => 'currency', 'align' => 'right', 'sort' => 'harga_tambahan'],
            [
                'label' => 'Harga Jual Total',
                'field' => 'total_price',
                'format' => 'currency',
                'align' => 'right',
                'computed' => fn (VarianMenu $varian): float => (float) ($varian->menu?->harga_jual ?? 0) + (float) $varian->harga_tambahan,
            ],
            [
                'label' => 'Bahan Resep',
                'field' => 'resep_count',
                'format' => 'count',
                'align' => 'right',
                'linkRoute' => 'admin.resep-menu.builder',
                'linkParam' => 'varian',
                'warnZero' => 'Perlu resep',
            ],
        ];
    }
}
