<?php

namespace App\Http\Controllers\Admin;

use App\Models\BahanBaku;
use App\Models\ResepMenu;
use App\Models\VarianMenu;
use Illuminate\Database\Eloquent\Model;

class ResepMenuController extends MasterDataController
{
    protected string $modelClass = ResepMenu::class;

    protected string $title = 'Resep Menu';

    protected string $description = 'Tentukan takaran bahan baku per varian menu sebagai dasar pemotongan stok POS dan hitung HPP.';

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
        [
            'name' => 'jumlah_bahan',
            'label' => 'Jumlah takaran bahan',
            'type' => 'number',
            'required' => true,
            'min' => '0.001',
            'max' => '999999999.999',
            'step' => 'any',
            'help' => 'Masukkan jumlah dalam satuan dasar bahan baku yang dipilih.',
        ],
    ];

    protected array $compositeUnique = [
        [
            'columns' => ['varian_id', 'bahan_id'],
            'errorField' => 'bahan_id',
            'message' => 'Bahan baku ini sudah ada di resep varian yang dipilih.',
        ],
    ];

    protected array $filters = [
        'varian_id' => [
            'label' => 'Varian',
            'column' => 'varian_id',
            'model' => VarianMenu::class,
            'optionValue' => 'varian_id',
            'optionLabel' => 'nama_varian',
            'optionLabelPrefix' => 'menu.nama_menu',
            'optionsWith' => ['menu'],
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

    public function __construct()
    {
        $this->columns = [
            ['label' => 'Menu Produk', 'field' => 'varian.menu.nama_menu'],
            ['label' => 'Varian', 'field' => 'varian.nama_varian'],
            ['label' => 'Bahan Baku', 'field' => 'bahan.nama_bahan'],
            [
                'label' => 'Takaran',
                'field' => 'jumlah_bahan',
                'format' => 'unit_amount',
                'unitField' => 'bahan.satuan',
                'align' => 'right',
                'sort' => 'jumlah_bahan',
            ],
            [
                'label' => 'Est. Biaya / Porsi',
                'field' => 'estimated_cost',
                'format' => 'currency',
                'align' => 'right',
                'computed' => function (ResepMenu $resep): float {
                    $bahan = $resep->bahan;
                    if ($bahan === null) {
                        return 0.0;
                    }

                    $unitCost = (float) $bahan->harga_beli / max((float) $bahan->isi_per_kemasan, 1.0);

                    return (float) $resep->jumlah_bahan * $unitCost;
                },
            ],
        ];
    }

    protected function extraFormData(?Model $record = null): array
    {
        return [
            'bahanUnits' => BahanBaku::query()->pluck('satuan', 'bahan_id')->all(),
        ];
    }
}
