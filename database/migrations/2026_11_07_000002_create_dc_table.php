<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dc', function (Blueprint $table) {
            $table->id('dc_id');
            $table->string('nama_dc');
            $table->text('alamat')->nullable();
            $table->string('telepon', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dc');
    }
};
