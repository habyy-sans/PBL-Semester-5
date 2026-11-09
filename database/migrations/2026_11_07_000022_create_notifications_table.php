<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id('notification_id');
            $table->foreignId('user_id')->constrained('pengguna', 'user_id')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description');
            $table->string('reference_type')->nullable();
            $table->bigInteger('reference_id')->nullable();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang', 'cabang_id')->nullOnDelete();
            $table->foreignId('dc_id')->nullable()->constrained('dc', 'dc_id')->nullOnDelete();
            $table->string('dedupe_key')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'dedupe_key'], 'notifications_user_dedupe_unique');
            $table->index(['user_id', 'cabang_id'], 'notifications_user_cabang_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
