<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['cabang_id', 'dc_id', 'diterbitkan_oleh', 'tanggal_kirim', 'status'])]
class SuratJalan extends Model
{
    protected $table = 'surat_jalan';

    protected $primaryKey = 'surat_jalan_id';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tanggal_kirim' => 'datetime',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function dc(): BelongsTo
    {
        return $this->belongsTo(Dc::class, 'dc_id');
    }

    public function diterbitkanOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'diterbitkan_oleh');
    }

    public function konfirmasi(): HasOne
    {
        return $this->hasOne(KonfirmasiPenerimaan::class, 'surat_jalan_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailSuratJalan::class, 'surat_jalan_id');
    }

    /**
     * Status computed untuk badge view:
     * - diterima  : baris konfirmasi_penerimaan sudah ada
     * - menunggu  : status DB 'dikirim' (barang sampai, menunggu pengecekan IC)
     * - dikirim   : status DB 'disiapkan'/'dikirim' tanpa konfirmasi
     */
    public function getComputedStatusAttribute(): string
    {
        if ($this->relationLoaded('konfirmasi') ? $this->konfirmasi !== null : $this->konfirmasi()->exists()) {
            return 'diterima';
        }

        return $this->status === 'dikirim' ? 'menunggu' : 'dikirim';
    }
}
