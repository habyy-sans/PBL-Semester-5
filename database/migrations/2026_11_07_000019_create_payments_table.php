<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('transaksi_id')->constrained('transaksi_penjualan', 'transaksi_id')->cascadeOnDelete();
            $table->string('metode');
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->string('status')->nullable()->default('berhasil');
            $table->timestamp('paid_at')->nullable();
            $table->decimal('jumlah_diterima', 12, 2)->nullable();
            $table->decimal('kembalian', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
