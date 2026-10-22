<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_dc', 'alamat', 'telepon'])]
class Dc extends Model
{
    protected $table = 'dc';

    protected $primaryKey = 'dc_id';

    public $timestamps = false;

    public function pengguna(): HasMany
    {
        return $this->hasMany(Pengguna::class, 'dc_id');
    }

    public function stok(): HasMany
    {
        return $this->hasMany(StokDc::class, 'dc_id');
    }
}
