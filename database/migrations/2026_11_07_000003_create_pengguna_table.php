<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengguna', function (Blueprint $table) {
            $table->id('user_id');
            $table->string('nama_lengkap');
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('telepon', 50)->nullable();
            $table->string('role');
            $table->foreignId('cabang_id')->nullable()->constrained('cabang', 'cabang_id')->nullOnDelete();
            $table->foreignId('dc_id')->nullable()->constrained('dc', 'dc_id')->nullOnDelete();
            $table->string('status')->default('aktif');
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengguna');
    }
};
