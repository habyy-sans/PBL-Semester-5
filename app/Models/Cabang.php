<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama_cabang', 'alamat', 'telepon'])]
class Cabang extends Model
{
    protected $table = 'cabang';

    protected $primaryKey = 'cabang_id';

    public $timestamps = false;
}
