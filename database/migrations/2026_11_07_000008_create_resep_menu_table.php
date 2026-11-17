<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resep_menu', function (Blueprint $table) {
            $table->id('resep_id');
            $table->foreignId('varian_id')->constrained('varian_menu', 'varian_id')->cascadeOnDelete();
            $table->foreignId('bahan_id')->constrained('bahan_baku', 'bahan_id')->cascadeOnDelete();
            $table->decimal('jumlah_bahan', 12, 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resep_menu');
    }
};
