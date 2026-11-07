<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transaksi_penjualan', 'no_transaksi')) {
            Schema::table('transaksi_penjualan', function (Blueprint $table) {
                $table->string('no_transaksi', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transaksi_penjualan', 'no_transaksi')) {
            Schema::table('transaksi_penjualan', function (Blueprint $table) {
                $table->dropColumn('no_transaksi');
            });
        }
    }
};
