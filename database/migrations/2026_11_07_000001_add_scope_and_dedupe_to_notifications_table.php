<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications') || Schema::hasColumn('notifications', 'cabang_id')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            // Cakupan penerima: IC & Manager dibatasi cabang, DC dibatasi dc.
            $table->foreignId('cabang_id')->nullable()->constrained('cabang', 'cabang_id')->nullOnDelete();
            $table->foreignId('dc_id')->nullable()->constrained('dc', 'dc_id')->nullOnDelete();

            // Kunci anti-duplikat: satu event hanya boleh membuat satu notifikasi per penerima.
            $table->string('dedupe_key')->nullable();

            $table->unique(['user_id', 'dedupe_key'], 'notifications_user_dedupe_unique');
            $table->index(['user_id', 'cabang_id'], 'notifications_user_cabang_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'cabang_id')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_user_dedupe_unique');
            $table->dropIndex('notifications_user_cabang_index');
            $table->dropConstrainedForeignId('cabang_id');
            $table->dropConstrainedForeignId('dc_id');
            $table->dropColumn('dedupe_key');
        });
    }
};
