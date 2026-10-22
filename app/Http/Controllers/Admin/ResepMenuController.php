<?php

namespace App\Http\Controllers\Admin;

use App\Models\BahanBaku;
use App\Models\ResepMenu;
use App\Models\VarianMenu;

class ResepMenuController extends MasterDataController
{
    protected string $modelClass = ResepMenu::class;

    protected string $title = 'Resep Menu';

    protected string $routeName = 'admin.resep-menu';

    protected array $fields = [
        [
            'name' => 'varian_id',
            'label' => 'Varian menu',
            'type' => 'select',
            'required' => true,
            'optionsModel' => VarianMenu::class,
            'optionValue' => 'varian_id',
            'optionLabel' => 'nama_varian',
            'optionLabelPrefix' => 'menu.nama_menu',
            'optionsWith' => ['menu'],
            'exists' => ['table' => 'varian_menu', 'column' => 'varian_id'],
        ],
        [
            'name' => 'bahan_id',
            'label' => 'Bahan baku',
            'type' => 'select',
            'required' => true,
            'optionsModel' => BahanBaku::class,
            'optionValue' => 'bahan_id',
            'optionLabel' => 'nama_bahan',
            'exists' => ['table' => 'bahan_baku', 'column' => 'bahan_id'],
        ],
        ['name' => 'jumlah_bahan', 'label' => 'Jumlah bahan', 'type' => 'number', 'required' => true, 'min' => '0.001', 'step' => '0.001'],
    ];

    protected array $columns = [
        ['label' => 'Menu', 'field' => 'varian.menu.nama_menu'],
        ['label' => 'Varian', 'field' => 'varian.nama_varian'],
        ['label' => 'Bahan baku', 'field' => 'bahan.nama_bahan'],
        ['label' => 'Jumlah', 'field' => 'jumlah_bahan'],
    ];

    protected array $filters = [
        'varian_id' => [
            'label' => 'Varian',
            'column' => 'varian_id',
            'model' => VarianMenu::class,
            'optionValue' => 'varian_id',
            'optionLabel' => 'nama_varian',
        ],
        'bahan_id' => [
            'label' => 'Bahan baku',
            'column' => 'bahan_id',
            'model' => BahanBaku::class,
            'optionValue' => 'bahan_id',
            'optionLabel' => 'nama_bahan',
        ],
    ];

    protected array $sortable = ['jumlah_bahan'];

    protected array $searchRelations = [
        'varian' => 'nama_varian',
        'varian.menu' => 'nama_menu',
        'bahan' => 'nama_bahan',
    ];

    protected array $with = ['varian.menu', 'bahan'];
}
