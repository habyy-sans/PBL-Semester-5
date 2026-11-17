<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('notifications') || ! Schema::hasTable('pengguna')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->foreignId('user_id')->constrained('pengguna', 'user_id')->onDelete('cascade');
            $table->string('type'); // pesanan_masuk, surat_jalan, restock, waste, stok_menipis
            $table->string('title');
            $table->text('description');
            $table->string('reference_type')->nullable(); // draft_instruksi, surat_jalan, waste_log, stok_cabang
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
