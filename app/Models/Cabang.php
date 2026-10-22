<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_cabang', 'alamat', 'telepon'])]
class Cabang extends Model
{
    protected $table = 'cabang';

    protected $primaryKey = 'cabang_id';

    public $timestamps = false;

    public function pengguna(): HasMany
    {
        return $this->hasMany(Pengguna::class, 'cabang_id');
    }

    public function stok(): HasMany
    {
        return $this->hasMany(StokCabang::class, 'cabang_id');
    }
}
