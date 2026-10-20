<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['surat_jalan_id', 'bahan_id', 'detail_draft_id', 'qty_dikirim', 'qty_diterima'])]
class DetailSuratJalan extends Model
{
    protected $table = 'detail_surat_jalan';

    protected $primaryKey = 'detail_sj_id';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'qty_dikirim' => 'decimal:3',
            'qty_diterima' => 'decimal:3',
        ];
    }

    public function suratJalan(): BelongsTo
    {
        return $this->belongsTo(SuratJalan::class, 'surat_jalan_id');
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_id');
    }

    public function detailDraft(): BelongsTo
    {
        return $this->belongsTo(DetailDraftInstruksi::class, 'detail_draft_id');
    }
}
