<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_log', function (Blueprint $table) {
            $table->id('waste_id');
            $table->foreignId('cabang_id')->constrained('cabang', 'cabang_id')->cascadeOnDelete();
            $table->foreignId('bahan_id')->constrained('bahan_baku', 'bahan_id')->cascadeOnDelete();
            $table->foreignId('kepala_shift_id')->nullable()->constrained('pengguna', 'user_id')->nullOnDelete();
            $table->decimal('qty', 12, 3);
            $table->text('alasan');
            $table->timestamp('tanggal')->useCurrent();
            $table->string('kelompok_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_log');
    }
};
