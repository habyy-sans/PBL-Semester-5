<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_surat_jalan', function (Blueprint $table) {
            $table->id('detail_sj_id');
            $table->foreignId('surat_jalan_id')->constrained('surat_jalan', 'surat_jalan_id')->cascadeOnDelete();
            $table->foreignId('bahan_id')->constrained('bahan_baku', 'bahan_id')->cascadeOnDelete();
            $table->foreignId('detail_draft_id')->nullable()->constrained('detail_draft_instruksi', 'detail_draft_id')->nullOnDelete();
            $table->decimal('qty_dikirim', 12, 3);
            $table->decimal('qty_diterima', 12, 3)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_surat_jalan');
    }
};
