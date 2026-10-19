<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cabang_id', 'bahan_id', 'tipe', 'pesan', 'status_baca', 'tanggal'])]
class NotifikasiAlert extends Model
{
    protected $table = 'notifikasi_alert';

    protected $primaryKey = 'alert_id';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tanggal' => 'datetime',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_id');
    }
}
