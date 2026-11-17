<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_produk', function (Blueprint $table) {
            $table->id('menu_id');
            $table->foreignId('kategori_menu_id')->constrained('kategori_menu', 'kategori_menu_id')->cascadeOnDelete();
            $table->string('nama_menu');
            $table->decimal('harga_jual', 12, 2)->default(0);
            $table->string('emoji', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_produk');
    }
};
