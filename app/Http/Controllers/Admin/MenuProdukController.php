<?php

namespace App\Http\Controllers\Admin;

use App\Models\KategoriMenu;
use App\Models\MenuProduk;
use App\Services\AdminMasterSyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MenuProdukController extends MasterDataController
{
    public function __construct(
        private readonly AdminMasterSyncService $sync,
    ) {}

    protected string $modelClass = MenuProduk::class;

    protected string $title = 'Menu Produk';

    protected string $description = 'Kelola daftar produk siap jual beserta kategori, harga dasar, dan kelengkapan variannya.';

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
        [
            'name' => 'nama_menu',
            'label' => 'Nama menu',
            'required' => true,
            'max' => 150,
            'uniqueCaseInsensitive' => true,
            'placeholder' => 'Contoh: Caramel Latte',
        ],
        [
            'name' => 'harga_jual',
            'label' => 'Harga dasar',
            'type' => 'number',
            'required' => true,
            'min' => 0,
            'max' => '999999999.99',
            'step' => 'any',
            'help' => 'Harga jual standar sebelum ditambah selisih harga varian.',
        ],
        [
            'name' => 'buat_varian_default',
            'label' => 'Buat varian default "Regular" secara otomatis',
            'type' => 'checkbox',
            'default' => true,
            'virtual' => true,
            'createOnly' => true,
            'help' => 'Kasir POS membutuhkan minimal 1 varian agar menu dapat dipilih dan dipasangi resep.',
        ],
    ];

    protected array $columns = [
        ['label' => 'Menu Produk', 'field' => 'nama_menu', 'sort' => 'nama_menu'],
        ['label' => 'Kategori', 'field' => 'kategori.nama_kategori'],
        ['label' => 'Harga Dasar', 'field' => 'harga_jual', 'format' => 'currency', 'align' => 'right', 'sort' => 'harga_jual'],
        [
            'label' => 'Varian',
            'field' => 'varian_count',
            'format' => 'count',
            'align' => 'right',
            'linkRoute' => 'admin.varian-menu.index',
            'linkParam' => 'menu_id',
            'warnZero' => 'Perlu varian',
        ],
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

    protected array $dependentLabels = [
        'varian' => 'varian menu',
    ];

    protected function extraFormData(?Model $record = null): array
    {
        if ($record !== null) {
            return [];
        }

        return [
            'recipeIngredients' => $this->sync->ingredientCatalogPayload(),
        ];
    }

    protected function validatedData(Request $request, ?Model $record = null): array
    {
        $validated = parent::validatedData($request, $record);

        if ($record !== null || ! ($validated['buat_varian_default'] ?? false)) {
            $validated['recipe_items'] = [];

            return $validated;
        }

        $rawItems = $request->input('recipe_items', []);
        $nonEmptyRows = [];

        if (is_array($rawItems)) {
            foreach ($rawItems as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $bahanId = trim((string) ($row['bahan_id'] ?? ''));
                $jumlah = trim((string) ($row['jumlah_bahan'] ?? ''));

                if ($bahanId !== '' || $jumlah !== '') {
                    $nonEmptyRows[$index] = [
                        'bahan_id' => $bahanId,
                        'jumlah_bahan' => $jumlah,
                    ];
                }
            }
        }

        if ($nonEmptyRows === []) {
            $validated['recipe_items'] = [];

            return $validated;
        }

        $recipeData = Validator::make(
            ['recipe_items' => $nonEmptyRows],
            [
                'recipe_items.*.bahan_id' => ['required', 'integer', 'distinct', Rule::exists('bahan_baku', 'bahan_id')],
                'recipe_items.*.jumlah_bahan' => ['required', 'numeric', 'gt:0', 'max:999999999.999'],
            ],
            [
                'recipe_items.*.bahan_id.required' => 'Pilih bahan baku pada baris resep yang diisi.',
                'recipe_items.*.bahan_id.distinct' => 'Bahan baku tidak boleh sama pada dua baris resep.',
                'recipe_items.*.jumlah_bahan.required' => 'Masukkan jumlah takaran bahan baku.',
                'recipe_items.*.jumlah_bahan.numeric' => 'Jumlah takaran bahan baku harus berupa angka.',
                'recipe_items.*.jumlah_bahan.gt' => 'Takaran bahan baku harus lebih dari 0.',
            ],
        )->validate();

        $validated['recipe_items'] = array_values($recipeData['recipe_items']);

        return $validated;
    }

    protected function afterStore(Model $record, Request $request, array $validated): ?RedirectResponse
    {
        if (! ($validated['buat_varian_default'] ?? false)) {
            return null;
        }

        /** @var MenuProduk $record */
        $varian = $this->sync->createDefaultVariantForMenu($record);
        $recipeItems = $validated['recipe_items'] ?? [];

        if ($recipeItems !== []) {
            $count = $this->sync->syncRecipeForVariant($varian, $recipeItems);

            return redirect()
                ->route($this->routeName.'.index')
                ->with('success', "Menu {$record->nama_menu}, varian \"Regular\", dan {$count} bahan resep berhasil disimpan.")
                ->with('success_action_label', 'Ubah Resep')
                ->with('success_action_url', route('admin.resep-menu.builder', $varian));
        }

        return redirect()
            ->route($this->routeName.'.index')
            ->with('success', "Menu {$record->nama_menu} beserta varian default \"Regular\" berhasil ditambahkan.")
            ->with('success_action_label', 'Atur Resep Menu')
            ->with('success_action_url', route('admin.resep-menu.builder', $varian));
    }
}
