<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['varian_id', 'bahan_id', 'jumlah_bahan'])]
class ResepMenu extends Model
{
    protected $table = 'resep_menu';

    protected $primaryKey = 'resep_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah_bahan' => 'decimal:3',
        ];
    }

    public function varian(): BelongsTo
    {
        return $this->belongsTo(VarianMenu::class, 'varian_id');
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_id');
    }
}
