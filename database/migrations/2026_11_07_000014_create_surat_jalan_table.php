<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_jalan', function (Blueprint $table) {
            $table->id('surat_jalan_id');
            $table->foreignId('cabang_id')->constrained('cabang', 'cabang_id')->cascadeOnDelete();
            $table->foreignId('dc_id')->constrained('dc', 'dc_id')->cascadeOnDelete();
            $table->foreignId('diterbitkan_oleh')->constrained('pengguna', 'user_id')->cascadeOnDelete();
            $table->timestamp('tanggal_kirim')->useCurrent();
            $table->string('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_jalan');
    }
};
