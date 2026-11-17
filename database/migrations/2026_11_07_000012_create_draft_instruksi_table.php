<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_instruksi', function (Blueprint $table) {
            $table->id('draft_id');
            $table->foreignId('cabang_id')->constrained('cabang', 'cabang_id')->cascadeOnDelete();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('pengguna', 'user_id')->nullOnDelete();
            $table->string('asal')->nullable();
            $table->string('status');
            $table->timestamp('dibuat_pada')->useCurrent();
            $table->foreignId('disetujui_oleh')->nullable()->constrained('pengguna', 'user_id')->nullOnDelete();
            $table->timestamp('tanggal_persetujuan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_instruksi');
    }
};
