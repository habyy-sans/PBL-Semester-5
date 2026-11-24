<?php

namespace App\Http\Controllers\Admin;

use App\Models\BahanBaku;
use App\Services\AdminMasterSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BahanBakuController extends MasterDataController
{
    public function __construct(
        private readonly AdminMasterSyncService $sync,
    ) {}

    protected string $modelClass = BahanBaku::class;

    protected string $title = 'Bahan Baku';

    protected string $description = 'Kelola katalog bahan baku global, harga beli, kemasan, dan minimum order (MOQ).';

    protected string $routeName = 'admin.bahan-baku';

    protected array $fields = [
        [
            'name' => 'nama_bahan',
            'label' => 'Nama bahan',
            'required' => true,
            'max' => 150,
            'uniqueCaseInsensitive' => true,
            'placeholder' => 'Contoh: Susu UHT Full Cream',
        ],
        [
            'name' => 'kategori',
            'label' => 'Kategori',
            'required' => true,
            'max' => 50,
            'placeholder' => 'Contoh: Dairy, Biji Kopi, Sirup',
        ],
        [
            'name' => 'satuan',
            'label' => 'Satuan dasar',
            'required' => true,
            'max' => 30,
            'placeholder' => 'Contoh: ml, g, pcs',
            'help' => 'Satuan terkecil untuk takaran resep dan pencatatan stok.',
        ],
        [
            'name' => 'harga_beli',
            'label' => 'Harga beli per kemasan',
            'type' => 'number',
            'required' => true,
            'min' => '0.01',
            'max' => '999999999.99',
            'step' => 'any',
            'help' => 'Harga beli untuk 1 kemasan utuh (dipakai untuk menghitung HPP).',
        ],
        [
            'name' => 'nama_kemasan',
            'label' => 'Nama kemasan',
            'required' => true,
            'max' => 30,
            'placeholder' => 'Contoh: Botol, Pack, Kg',
        ],
        [
            'name' => 'isi_per_kemasan',
            'label' => 'Isi per kemasan',
            'type' => 'number',
            'required' => true,
            'min' => '0.001',
            'max' => '999999999.999',
            'step' => 'any',
            'help' => 'Jumlah satuan dasar di dalam 1 kemasan (misal 1000 untuk 1 liter).',
        ],
        [
            'name' => 'minimum_order_qty',
            'label' => 'Minimum Order (MOQ)',
            'type' => 'integer',
            'required' => true,
            'min' => 1,
            'max' => 100000,
            'default' => 1,
            'help' => 'Jumlah kemasan minimal per pengajuan restock ke DC.',
        ],
    ];

    protected array $columns = [
        ['label' => 'Nama Bahan', 'field' => 'nama_bahan', 'sort' => 'nama_bahan'],
        ['label' => 'Kategori', 'field' => 'kategori', 'sort' => 'kategori'],
        ['label' => 'Harga Beli', 'field' => 'harga_beli', 'format' => 'currency', 'align' => 'right', 'sort' => 'harga_beli'],
        ['label' => 'Kemasan & Isi', 'field' => 'isi_per_kemasan', 'format' => 'package_info'],
        ['label' => 'MOQ', 'field' => 'minimum_order_qty', 'format' => 'moq'],
        [
            'label' => 'Resep',
            'field' => 'resep_count',
            'format' => 'count',
            'align' => 'right',
            'linkRoute' => 'admin.resep-menu.index',
            'linkParam' => 'bahan_id',
        ],
    ];

    protected array $searchable = ['nama_bahan', 'kategori', 'satuan'];

    protected array $filters = [
        'kategori' => [
            'label' => 'Kategori bahan',
            'column' => 'kategori',
            'model' => BahanBaku::class,
            'distinct' => 'kategori',
            'type' => 'string',
        ],
    ];

    protected array $sortable = ['nama_bahan', 'kategori', 'harga_beli'];

    protected array $withCount = ['resep'];

    protected array $dependents = [
        'stokCabang',
        'stokDc',
        'resep',
        'stockMovements',
        'wasteLogs',
        'detailDraftInstruksi',
        'detailSuratJalan',
        'notifikasiAlert',
    ];

    protected array $dependentLabels = [
        'stokCabang' => 'stok cabang aktif',
        'stokDc' => 'stok gudang DC aktif',
        'resep' => 'resep menu',
        'stockMovements' => 'riwayat mutasi stok',
        'wasteLogs' => 'catatan waste',
        'detailDraftInstruksi' => 'permintaan restock',
        'detailSuratJalan' => 'surat jalan pengiriman',
        'notifikasiAlert' => 'riwayat alert cabang',
    ];

    protected function afterStore(Model $record, Request $request, array $validated): ?RedirectResponse
    {
        /** @var BahanBaku $record */
        $created = $this->sync->initializeStockForIngredient($record);

        return redirect()
            ->route($this->routeName.'.index')
            ->with('success', "Bahan Baku {$record->nama_bahan} berhasil ditambahkan dan stok awal disiapkan di {$created['cabang']} cabang serta {$created['dc']} DC.");
    }

    protected function hasBlockingDependency(Model $model, string $relation): bool
    {
        if ($relation === 'stokCabang') {
            return $model->stokCabang()
                ->where(function (Builder $query): void {
                    $query->where('jumlah_stok', '>', 0)->orWhere('stok_minimum', '>', 0);
                })
                ->exists();
        }

        if ($relation === 'stokDc') {
            return $model->stokDc()
                ->where(function (Builder $query): void {
                    $query->where('jumlah_stok', '>', 0)->orWhere('stok_minimum', '>', 0);
                })
                ->exists();
        }

        return parent::hasBlockingDependency($model, $relation);
    }

    protected function beforeDelete(Model $model): void
    {
        /** @var BahanBaku $model */
        $this->sync->pruneZeroStockForIngredient($model);
    }
}
