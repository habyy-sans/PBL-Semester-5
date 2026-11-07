<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_cabang', function (Blueprint $table) {
            $table->id('stok_cabang_id');
            $table->foreignId('cabang_id')->constrained('cabang', 'cabang_id')->cascadeOnDelete();
            $table->foreignId('bahan_id')->constrained('bahan_baku', 'bahan_id')->cascadeOnDelete();
            $table->decimal('jumlah_stok', 12, 3)->default(0);
            $table->decimal('stok_minimum', 12, 3)->default(0);
            $table->unique(['cabang_id', 'bahan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_cabang');
    }
};
