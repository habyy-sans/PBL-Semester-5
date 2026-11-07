<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabang', function (Blueprint $table) {
            $table->id('cabang_id');
            $table->string('nama_cabang');
            $table->text('alamat')->nullable();
            $table->string('telepon', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabang');
    }
};
