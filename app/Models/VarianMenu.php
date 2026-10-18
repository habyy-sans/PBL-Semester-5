<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['menu_id', 'nama_varian', 'harga_tambahan'])]
class VarianMenu extends Model
{
    protected $table = 'varian_menu';

    protected $primaryKey = 'varian_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'harga_tambahan' => 'decimal:2',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(MenuProduk::class, 'menu_id');
    }

    public function resep(): HasMany
    {
        return $this->hasMany(ResepMenu::class, 'varian_id');
    }
}
