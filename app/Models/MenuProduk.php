<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kategori_menu_id', 'nama_menu', 'harga_jual', 'emoji'])]
class MenuProduk extends Model
{
    protected $table = 'menu_produk';

    protected $primaryKey = 'menu_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'harga_jual' => 'decimal:2',
        ];
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriMenu::class, 'kategori_menu_id');
    }

    public function varian(): HasMany
    {
        return $this->hasMany(VarianMenu::class, 'menu_id');
    }
}
