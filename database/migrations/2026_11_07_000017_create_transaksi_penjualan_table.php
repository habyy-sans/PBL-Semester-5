<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_penjualan', function (Blueprint $table) {
            $table->id('transaksi_id');
            $table->string('no_transaksi', 50)->nullable();
            $table->foreignId('cabang_id')->constrained('cabang', 'cabang_id')->cascadeOnDelete();
            $table->foreignId('kasir_id')->nullable()->constrained('pengguna', 'user_id')->nullOnDelete();
            $table->timestamp('tanggal')->useCurrent();
            $table->decimal('total_bayar', 12, 2)->default(0);
            $table->string('status');
            $table->text('alasan_batal')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_penjualan');
    }
};
