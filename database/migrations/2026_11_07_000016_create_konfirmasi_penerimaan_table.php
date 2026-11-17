<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konfirmasi_penerimaan', function (Blueprint $table) {
            $table->id('konfirmasi_id');
            $table->foreignId('surat_jalan_id')->constrained('surat_jalan', 'surat_jalan_id')->cascadeOnDelete();
            $table->foreignId('kepala_shift_id')->constrained('pengguna', 'user_id')->cascadeOnDelete();
            $table->timestamp('tanggal_konfirmasi')->useCurrent();
            $table->text('kondisi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konfirmasi_penerimaan');
    }
};
