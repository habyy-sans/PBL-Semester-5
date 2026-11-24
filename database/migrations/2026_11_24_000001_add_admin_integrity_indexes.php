<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kategori_menu') && ! Schema::hasIndex('kategori_menu', 'kategori_menu_nama_unique')) {
            Schema::table('kategori_menu', function (Blueprint $table): void {
                $table->unique('nama_kategori', 'kategori_menu_nama_unique');
            });
        }

        if (Schema::hasTable('varian_menu') && ! Schema::hasIndex('varian_menu', 'varian_menu_menu_nama_unique')) {
            Schema::table('varian_menu', function (Blueprint $table): void {
                $table->unique(['menu_id', 'nama_varian'], 'varian_menu_menu_nama_unique');
            });
        }

        if (Schema::hasTable('resep_menu') && ! Schema::hasIndex('resep_menu', 'resep_menu_varian_bahan_unique')) {
            Schema::table('resep_menu', function (Blueprint $table): void {
                $table->unique(['varian_id', 'bahan_id'], 'resep_menu_varian_bahan_unique');
            });
        }

        if (Schema::hasTable('pengguna')) {
            if (! Schema::hasIndex('pengguna', 'pengguna_username_unique')) {
                Schema::table('pengguna', function (Blueprint $table): void {
                    $table->unique('username', 'pengguna_username_unique');
                });
            }

            if (! Schema::hasIndex('pengguna', 'pengguna_role_status_index')) {
                Schema::table('pengguna', function (Blueprint $table): void {
                    $table->index(['role', 'status'], 'pengguna_role_status_index');
                });
            }
        }

        if (Schema::hasTable('stok_cabang') && ! Schema::hasIndex('stok_cabang', 'stok_cabang_cabang_qty_index')) {
            Schema::table('stok_cabang', function (Blueprint $table): void {
                $table->index(['cabang_id', 'jumlah_stok'], 'stok_cabang_cabang_qty_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stok_cabang') && Schema::hasIndex('stok_cabang', 'stok_cabang_cabang_qty_index')) {
            Schema::table('stok_cabang', function (Blueprint $table): void {
                $table->dropIndex('stok_cabang_cabang_qty_index');
            });
        }

        if (Schema::hasTable('pengguna')) {
            if (Schema::hasIndex('pengguna', 'pengguna_role_status_index')) {
                Schema::table('pengguna', function (Blueprint $table): void {
                    $table->dropIndex('pengguna_role_status_index');
                });
            }

            if (Schema::hasIndex('pengguna', 'pengguna_username_unique')) {
                Schema::table('pengguna', function (Blueprint $table): void {
                    $table->dropUnique('pengguna_username_unique');
                });
            }
        }

        if (Schema::hasTable('resep_menu') && Schema::hasIndex('resep_menu', 'resep_menu_varian_bahan_unique')) {
            Schema::table('resep_menu', function (Blueprint $table): void {
                $table->dropUnique('resep_menu_varian_bahan_unique');
            });
        }

        if (Schema::hasTable('varian_menu') && Schema::hasIndex('varian_menu', 'varian_menu_menu_nama_unique')) {
            Schema::table('varian_menu', function (Blueprint $table): void {
                $table->dropUnique('varian_menu_menu_nama_unique');
            });
        }

        if (Schema::hasTable('kategori_menu') && Schema::hasIndex('kategori_menu', 'kategori_menu_nama_unique')) {
            Schema::table('kategori_menu', function (Blueprint $table): void {
                $table->dropUnique('kategori_menu_nama_unique');
            });
        }
    }
};
