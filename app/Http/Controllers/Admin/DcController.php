<?php

namespace App\Http\Controllers\Admin;

use App\Models\Dc;

class DcController extends MasterDataController
{
    protected string $modelClass = Dc::class;

    protected string $title = 'Distribution Center';

    protected string $routeName = 'admin.dc';

    protected array $fields = [
        ['name' => 'nama_dc', 'label' => 'Nama DC', 'required' => true, 'max' => 100],
        ['name' => 'alamat', 'label' => 'Alamat', 'type' => 'textarea', 'required' => true, 'max' => 2000],
        ['name' => 'telepon', 'label' => 'Telepon', 'required' => false, 'max' => 20],
    ];

    protected array $columns = [
        ['label' => 'Nama DC', 'field' => 'nama_dc'],
        ['label' => 'Alamat', 'field' => 'alamat'],
        ['label' => 'Telepon', 'field' => 'telepon'],
        ['label' => 'Pengguna', 'field' => 'pengguna_count'],
        ['label' => 'Pengguna', 'field' => 'pengguna_count'],
    ];

    protected array $searchable = ['nama_dc', 'alamat', 'telepon'];

    protected array $sortable = ['nama_dc'];

    protected array $withCount = ['pengguna'];

    protected array $dependents = ['pengguna', 'stok'];
}
