<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surat_jalan_id', 'kepala_shift_id', 'tanggal_konfirmasi', 'kondisi'])]
class KonfirmasiPenerimaan extends Model
{
    protected $table = 'konfirmasi_penerimaan';

    protected $primaryKey = 'konfirmasi_id';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tanggal_konfirmasi' => 'datetime',
        ];
    }

    public function suratJalan(): BelongsTo
    {
        return $this->belongsTo(SuratJalan::class, 'surat_jalan_id');
    }

    public function kepalaShift(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'kepala_shift_id');
    }
}
