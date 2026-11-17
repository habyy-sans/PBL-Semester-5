<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('varian_menu', function (Blueprint $table) {
            $table->id('varian_id');
            $table->foreignId('menu_id')->constrained('menu_produk', 'menu_id')->cascadeOnDelete();
            $table->string('nama_varian');
            $table->decimal('harga_tambahan', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('varian_menu');
    }
};
