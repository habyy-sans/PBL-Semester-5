<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['dc_id', 'bahan_id', 'jumlah_stok', 'stok_minimum'])]
class StokDc extends Model
{
    protected $table = 'stok_dc';

    protected $primaryKey = 'stok_dc_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah_stok' => 'decimal:3',
            'stok_minimum' => 'decimal:3',
        ];
    }
}
