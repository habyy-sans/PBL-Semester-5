<?php

namespace App\Http\Controllers\Admin;

use App\Models\BahanBaku;

class BahanBakuController extends MasterDataController
{
    protected string $modelClass = BahanBaku::class;

    protected string $title = 'Bahan Baku';

    protected string $routeName = 'admin.bahan-baku';

    protected array $fields = [
        ['name' => 'nama_bahan', 'label' => 'Nama bahan', 'required' => true, 'max' => 150],
        ['name' => 'kategori', 'label' => 'Kategori', 'required' => true, 'max' => 50],
        ['name' => 'satuan', 'label' => 'Satuan', 'required' => true, 'max' => 30],
        ['name' => 'harga_beli', 'label' => 'Harga beli', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '0.01'],
        ['name' => 'nama_kemasan', 'label' => 'Nama kemasan', 'required' => true, 'max' => 30],
        ['name' => 'isi_per_kemasan', 'label' => 'Isi per kemasan', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '0.001'],
    ];

    protected array $columns = [
        ['label' => 'Nama bahan', 'field' => 'nama_bahan'],
        ['label' => 'Kategori', 'field' => 'kategori'],
        ['label' => 'Satuan', 'field' => 'satuan'],
        ['label' => 'Harga beli', 'field' => 'harga_beli', 'format' => 'currency'],
        ['label' => 'Kemasan', 'field' => 'nama_kemasan'],
        ['label' => 'Isi / kemasan', 'field' => 'isi_per_kemasan'],
    ];

    protected array $searchable = ['nama_bahan', 'kategori'];

    protected array $filters = [
        'kategori' => [
            'label' => 'Kategori bahan',
            'column' => 'kategori',
            'model' => BahanBaku::class,
            'distinct' => 'kategori',
        ],
    ];

    protected array $sortable = ['nama_bahan', 'kategori', 'harga_beli'];

    protected array $dependents = ['stokCabang', 'stokDc', 'resep'];
}
