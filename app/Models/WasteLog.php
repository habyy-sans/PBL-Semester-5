<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cabang_id', 'bahan_id', 'kepala_shift_id', 'qty', 'alasan', 'tanggal', 'kelompok_id'])]
class WasteLog extends Model
{
    protected $table = 'waste_log';

    protected $primaryKey = 'waste_id';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
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

    public function kepalaShift(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'kepala_shift_id');
    }
}
