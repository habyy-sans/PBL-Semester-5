<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bahan_baku', function (Blueprint $table) {
            $table->id('bahan_id');
            $table->string('nama_bahan');
            $table->string('kategori');
            $table->string('satuan');
            $table->string('nama_kemasan')->nullable();
            $table->decimal('isi_per_kemasan', 12, 3)->default(0);
            $table->decimal('harga_beli', 12, 2)->default(0);
            $table->integer('minimum_order_qty')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahan_baku');
    }
};
