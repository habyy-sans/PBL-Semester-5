<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bahan_id',
    'cabang_id',
    'dc_id',
    'jenis_mutasi',
    'jumlah',
    'referensi_id',
    'tanggal',
    'keterangan',
    'kelompok_id',
])]
class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $primaryKey = 'movement_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:3',
            'tanggal' => 'datetime',
        ];
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_id');
    }
}
