<?php

namespace App\Http\Controllers\Admin;

use App\Models\Dc;
use App\Services\AdminMasterSyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DcController extends MasterDataController
{
    public function __construct(
        private readonly AdminMasterSyncService $sync,
    ) {}

    protected string $modelClass = Dc::class;

    protected string $title = 'Distribution Center';

    protected string $description = 'Kelola fasilitas gudang logistik pusat dan akses ke pengguna serta stok gudang DC.';

    protected string $routeName = 'admin.dc';

    protected array $fields = [
        [
            'name' => 'nama_dc',
            'label' => 'Nama DC',
            'required' => true,
            'max' => 100,
            'uniqueCaseInsensitive' => true,
            'placeholder' => 'Contoh: Distribution Center Pasuruan',
        ],
        [
            'name' => 'alamat',
            'label' => 'Alamat',
            'type' => 'textarea',
            'required' => true,
            'max' => 2000,
            'placeholder' => 'Masukkan alamat lengkap Distribution Center',
        ],
        [
            'name' => 'telepon',
            'label' => 'Telepon',
            'required' => false,
            'max' => 20,
            'placeholder' => 'Contoh: 0343-5550100',
        ],
    ];

    protected array $columns = [
        ['label' => 'Nama DC', 'field' => 'nama_dc', 'sort' => 'nama_dc'],
        ['label' => 'Alamat', 'field' => 'alamat'],
        ['label' => 'Telepon', 'field' => 'telepon'],
        [
            'label' => 'Pengguna',
            'field' => 'pengguna_count',
            'format' => 'count',
            'align' => 'right',
            'linkRoute' => 'admin.pengguna.index',
            'linkParam' => 'dc_id',
        ],
        [
            'label' => 'Stok Gudang',
            'field' => 'dc_id',
            'format' => 'action_link',
            'linkText' => 'Lihat stok',
            'linkRoute' => 'admin.stok-dc.index',
            'linkParam' => 'dc_id',
        ],
    ];

    protected array $searchable = ['nama_dc', 'alamat', 'telepon'];

    protected array $sortable = ['nama_dc'];

    protected array $withCount = ['pengguna'];

    protected array $dependents = ['pengguna', 'stok', 'suratJalan', 'stockMovements'];

    protected array $dependentLabels = [
        'pengguna' => 'akun pengguna DC',
        'stok' => 'stok gudang DC aktif',
        'suratJalan' => 'surat jalan pengiriman',
        'stockMovements' => 'riwayat mutasi stok',
    ];

    protected function afterStore(Model $record, Request $request, array $validated): ?RedirectResponse
    {
        /** @var Dc $record */
        $count = $this->sync->initializeStockForDc($record);

        return redirect()
            ->route($this->routeName.'.index')
            ->with('success', "Distribution Center {$record->nama_dc} berhasil ditambahkan dan {$count} baris stok bahan awal disiapkan.");
    }

    protected function hasBlockingDependency(Model $model, string $relation): bool
    {
        if ($relation === 'stok') {
            /** @var Dc $model */
            return $this->sync->hasActiveStockForDc($model);
        }

        return parent::hasBlockingDependency($model, $relation);
    }

    protected function beforeDelete(Model $model): void
    {
        /** @var Dc $model */
        $this->sync->pruneZeroStockForDc($model);
    }
}
