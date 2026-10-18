<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'cabang_id',
    'kasir_id',
    'tanggal',
    'total_bayar',
    'status',
    'alasan_batal',
])]
class TransaksiPenjualan extends Model
{
    protected $table = 'transaksi_penjualan';

    protected $primaryKey = 'transaksi_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'datetime',
            'total_bayar' => 'decimal:2',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'kasir_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailTransaksi::class, 'transaksi_id');
    }

    public function pembayaran(): HasOne
    {
        return $this->hasOne(Payment::class, 'transaksi_id');
    }
}
