<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bahan_id',
    'cabang_id',
    'dc_id',
    'jenis_mutasi',
    'jumlah',
    'referensi_id',
    'tanggal',
    'keterangan',
    'kelompok_id',
])]
/**
 * Mutasi stok (DC maupun cabang).
 *
 * Kontrak referensi (disepakati, tanpa perubahan schema):
 * - referensi_id (bigint) SELALU menunjuk ke baris dokumen sumber, bukan UUID:
 *   - jenis_mutasi 'waste'           => waste_log.waste_id
 *   - jenis_mutasi 'penerimaan_cabang' => surat_jalan.surat_jalan_id
 *   - jenis_mutasi 'penjualan'       => transaksi_penjualan.transaksi_id
 * - kelompok_id (uuid) dipakai untuk mengelompokkan banyak baris dari satu
 *   transaksi yang sama (misal 1 POST waste multi-bahan). UUID tidak pernah
 *   dimasukkan ke referensi_id karena kolomnya bertipe bigint.
 */
class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $primaryKey = 'movement_id';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:3',
            'tanggal' => 'datetime',
        ];
    }

    public function bahan(): BelongsTo
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_id');
    }
}
