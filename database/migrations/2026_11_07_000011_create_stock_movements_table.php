<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id('movement_id');
            $table->foreignId('bahan_id')->constrained('bahan_baku', 'bahan_id')->cascadeOnDelete();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang', 'cabang_id')->nullOnDelete();
            $table->foreignId('dc_id')->nullable()->constrained('dc', 'dc_id')->nullOnDelete();
            $table->string('jenis_mutasi');
            $table->decimal('jumlah', 12, 3);
            $table->bigInteger('referensi_id')->nullable();
            $table->timestamp('tanggal')->useCurrent();
            $table->text('keterangan')->nullable();
            $table->string('kelompok_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
