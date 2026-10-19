<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['draft_id', 'bahan_id', 'qty_diminta'])]
class DetailDraftInstruksi extends Model
{
    protected $table = 'detail_draft_instruksi';

    protected $primaryKey = 'detail_draft_id';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'qty_diminta' => 'decimal:3',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(DraftInstruksi::class, 'draft_id');
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_id');
    }

    public function suratJalanDetail(): HasMany
    {
        return $this->hasMany(DetailSuratJalan::class, 'detail_draft_id');
    }
}
