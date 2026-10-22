<?php

namespace App\Http\Controllers\Admin;

use App\Models\Cabang;

class CabangController extends MasterDataController
{
    protected string $modelClass = Cabang::class;

    protected string $title = 'Cabang';

    protected string $routeName = 'admin.cabang';

    protected array $fields = [
        ['name' => 'nama_cabang', 'label' => 'Nama cabang', 'required' => true, 'max' => 100],
        ['name' => 'alamat', 'label' => 'Alamat', 'type' => 'textarea', 'required' => true, 'max' => 2000],
        ['name' => 'telepon', 'label' => 'Telepon', 'required' => false, 'max' => 20],
    ];

    protected array $columns = [
        ['label' => 'Nama cabang', 'field' => 'nama_cabang'],
        ['label' => 'Alamat', 'field' => 'alamat'],
        ['label' => 'Telepon', 'field' => 'telepon'],
        ['label' => 'Pengguna', 'field' => 'pengguna_count'],
    ];

    protected array $searchable = ['nama_cabang', 'alamat', 'telepon'];

    protected array $sortable = ['nama_cabang'];

    protected array $withCount = ['pengguna'];

    protected array $dependents = ['pengguna', 'stok'];
}
