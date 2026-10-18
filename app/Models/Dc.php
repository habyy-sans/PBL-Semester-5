<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama_dc', 'alamat', 'telepon'])]
class Dc extends Model
{
    protected $table = 'dc';

    protected $primaryKey = 'dc_id';

    public $timestamps = false;
}
