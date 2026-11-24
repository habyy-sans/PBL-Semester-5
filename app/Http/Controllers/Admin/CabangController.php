<?php

namespace App\Http\Controllers\Admin;

use App\Models\Cabang;
use App\Services\AdminMasterSyncService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CabangController extends MasterDataController
{
    public function __construct(
        private readonly AdminMasterSyncService $sync,
    ) {}

    protected string $modelClass = Cabang::class;

    protected string $title = 'Cabang';

    protected string $description = 'Kelola daftar gerai cabang operasional beserta ringkasan pengguna dan stoknya.';

    protected string $routeName = 'admin.cabang';

    protected array $fields = [
        [
            'name' => 'nama_cabang',
            'label' => 'Nama cabang',
            'required' => true,
            'max' => 100,
            'uniqueCaseInsensitive' => true,
            'placeholder' => 'Contoh: Malang - Soekarno Hatta',
        ],
        [
            'name' => 'alamat',
            'label' => 'Alamat',
            'type' => 'textarea',
            'required' => true,
            'max' => 2000,
            'placeholder' => 'Masukkan alamat lengkap cabang',
        ],
        [
            'name' => 'telepon',
            'label' => 'Telepon',
            'required' => false,
            'max' => 20,
            'placeholder' => 'Contoh: 0341-5550123',
        ],
    ];

    protected array $columns = [
        ['label' => 'Nama Cabang', 'field' => 'nama_cabang', 'sort' => 'nama_cabang'],
        ['label' => 'Alamat', 'field' => 'alamat'],
        ['label' => 'Telepon', 'field' => 'telepon'],
        [
            'label' => 'Pengguna',
            'field' => 'pengguna_count',
            'format' => 'count',
            'align' => 'right',
            'linkRoute' => 'admin.pengguna.index',
            'linkParam' => 'cabang_id',
        ],
        [
            'label' => 'Stok',
            'field' => 'cabang_id',
            'format' => 'action_link',
            'linkText' => 'Lihat stok',
            'linkRoute' => 'admin.stok-cabang.index',
            'linkParam' => 'cabang_id',
        ],
    ];

    protected array $searchable = ['nama_cabang', 'alamat', 'telepon'];

    protected array $sortable = ['nama_cabang'];

    protected array $withCount = ['pengguna'];

    protected array $dependents = [
        'pengguna',
        'stok',
        'transaksiPenjualan',
        'suratJalan',
        'draftInstruksi',
        'wasteLogs',
        'notifikasiAlert',
    ];

    protected array $dependentLabels = [
        'pengguna' => 'akun pengguna',
        'stok' => 'stok cabang aktif',
        'transaksiPenjualan' => 'transaksi penjualan',
        'suratJalan' => 'surat jalan pengiriman',
        'draftInstruksi' => 'permintaan restock',
        'wasteLogs' => 'catatan waste',
        'notifikasiAlert' => 'riwayat alert cabang',
    ];

    protected function afterStore(Model $record, Request $request, array $validated): ?RedirectResponse
    {
        /** @var Cabang $record */
        $count = $this->sync->initializeStockForBranch($record);

        return redirect()
            ->route($this->routeName.'.index')
            ->with('success', "Cabang {$record->nama_cabang} berhasil ditambahkan dan {$count} baris stok bahan awal disiapkan.");
    }

    protected function hasBlockingDependency(Model $model, string $relation): bool
    {
        if ($relation === 'stok') {
            /** @var Cabang $model */
            return $this->sync->hasActiveStockForBranch($model);
        }

        return parent::hasBlockingDependency($model, $relation);
    }

    protected function beforeDelete(Model $model): void
    {
        /** @var Cabang $model */
        $this->sync->pruneZeroStockForBranch($model);
    }
}
