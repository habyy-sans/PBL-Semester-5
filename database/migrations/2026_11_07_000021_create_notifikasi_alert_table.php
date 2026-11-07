<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi_alert', function (Blueprint $table) {
            $table->id('alert_id');
            $table->foreignId('cabang_id')->constrained('cabang', 'cabang_id')->cascadeOnDelete();
            $table->foreignId('bahan_id')->nullable()->constrained('bahan_baku', 'bahan_id')->nullOnDelete();
            $table->string('tipe');
            $table->text('pesan');
            $table->boolean('status_baca')->default(false);
            $table->timestamp('tanggal')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_alert');
    }
};
