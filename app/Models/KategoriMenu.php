<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_kategori'])]
class KategoriMenu extends Model
{
    protected $table = 'kategori_menu';

    protected $primaryKey = 'kategori_menu_id';

    public $timestamps = false;

    public function menuProduk(): HasMany
    {
        return $this->hasMany(MenuProduk::class, 'kategori_menu_id');
    }
}
