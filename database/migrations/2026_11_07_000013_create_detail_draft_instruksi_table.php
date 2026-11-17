<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_draft_instruksi', function (Blueprint $table) {
            $table->id('detail_draft_id');
            $table->foreignId('draft_id')->constrained('draft_instruksi', 'draft_id')->cascadeOnDelete();
            $table->foreignId('bahan_id')->constrained('bahan_baku', 'bahan_id')->cascadeOnDelete();
            $table->decimal('qty_diminta', 12, 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_draft_instruksi');
    }
};
