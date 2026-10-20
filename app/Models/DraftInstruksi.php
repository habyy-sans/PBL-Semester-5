<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['cabang_id', 'dibuat_oleh', 'asal', 'status', 'dibuat_pada', 'disetujui_oleh', 'tanggal_persetujuan'])]
class DraftInstruksi extends Model
{
    protected $table = 'draft_instruksi';

    protected $primaryKey = 'draft_id';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dibuat_pada' => 'datetime',
            'tanggal_persetujuan' => 'datetime',
        ];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh');
    }

    public function disetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'disetujui_oleh');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailDraftInstruksi::class, 'draft_id');
    }
}
