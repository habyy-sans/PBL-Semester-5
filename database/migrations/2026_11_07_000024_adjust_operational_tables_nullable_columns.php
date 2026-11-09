<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "transaksi_penjualan" ALTER COLUMN "kasir_id" DROP NOT NULL');
            DB::statement('ALTER TABLE "surat_jalan" ALTER COLUMN "diterbitkan_oleh" DROP NOT NULL');
            DB::statement("ALTER TABLE \"surat_jalan\" ALTER COLUMN \"status\" SET DEFAULT 'disiapkan'");
            DB::statement('ALTER TABLE "draft_instruksi" ALTER COLUMN "dibuat_oleh" DROP NOT NULL');
            DB::statement('ALTER TABLE "draft_instruksi" ALTER COLUMN "asal" DROP NOT NULL');
            DB::statement('ALTER TABLE "detail_surat_jalan" ALTER COLUMN "qty_diterima" DROP NOT NULL');
            DB::statement('ALTER TABLE "detail_surat_jalan" ALTER COLUMN "qty_diterima" DROP DEFAULT');
            DB::statement('ALTER TABLE "waste_log" ALTER COLUMN "kepala_shift_id" DROP NOT NULL');
            DB::statement('ALTER TABLE "payments" ALTER COLUMN "status" DROP NOT NULL');
            DB::statement("ALTER TABLE \"payments\" ALTER COLUMN \"status\" SET DEFAULT 'berhasil'");
        }
    }

    public function down(): void
    {
        // No-op for rollback safety
    }
};
