<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nama_bahan',
    'kategori',
    'satuan',
    'nama_kemasan',
    'isi_per_kemasan',
    'harga_beli',
    'minimum_order_qty',
])]
class BahanBaku extends Model
{
    protected $table = 'bahan_baku';

    protected $primaryKey = 'bahan_id';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'isi_per_kemasan' => 'decimal:3',
            'harga_beli' => 'decimal:2',
            'minimum_order_qty' => 'integer',
        ];
    }

    public function stokCabang(): HasMany
    {
        return $this->hasMany(StokCabang::class, 'bahan_id');
    }

    public function stokDc(): HasMany
    {
        return $this->hasMany(StokDc::class, 'bahan_id');
    }

    public function resep(): HasMany
    {
        return $this->hasMany(ResepMenu::class, 'bahan_id');
    }
}
