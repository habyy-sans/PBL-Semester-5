<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\DetailSuratJalan;
use App\Models\KonfirmasiPenerimaan;
use App\Models\Pengguna;
use App\Models\StockMovement;
use App\Models\StokCabang;
use App\Models\SuratJalan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReceivingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Skema minimal untuk sqlite in-memory (tabel domain asli ada di
        // Supabase dan tidak dimigrasikan lewat Laravel migrations).
        Schema::create('pengguna', function ($table) {
            $table->id('user_id');
            $table->string('nama_lengkap', 150);
            $table->string('username', 50)->unique();
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('telepon', 20)->nullable();
            $table->string('role', 50);
            $table->bigInteger('cabang_id')->nullable();
            $table->bigInteger('dc_id')->nullable();
            $table->string('status', 20)->default('aktif');
            $table->string('remember_token', 100)->nullable();
        });

        Schema::create('bahan_baku', function ($table) {
            $table->id('bahan_id');
            $table->string('nama_bahan', 100);
            $table->string('satuan', 20);
            $table->decimal('harga_beli', 12, 2)->default(0);
            $table->string('nama_kemasan', 50)->nullable();
            $table->decimal('isi_per_kemasan', 12, 3)->default(1);
            $table->string('kategori', 50)->nullable();
        });

        Schema::create('stok_cabang', function ($table) {
            $table->id('stok_cabang_id');
            $table->bigInteger('cabang_id');
            $table->bigInteger('bahan_id');
            $table->decimal('jumlah_stok', 14, 3)->default(0);
            $table->decimal('stok_minimum', 14, 3)->default(0);
        });

        Schema::create('surat_jalan', function ($table) {
            $table->id('surat_jalan_id');
            $table->bigInteger('cabang_id');
            $table->bigInteger('dc_id');
            $table->bigInteger('diterbitkan_oleh')->nullable();
            $table->timestamp('tanggal_kirim')->nullable();
            $table->string('status', 20)->default('disiapkan');
        });

        Schema::create('detail_surat_jalan', function ($table) {
            $table->id('detail_sj_id');
            $table->bigInteger('surat_jalan_id');
            $table->bigInteger('bahan_id');
            $table->bigInteger('detail_draft_id')->nullable();
            $table->decimal('qty_dikirim', 14, 3);
            $table->decimal('qty_diterima', 14, 3)->nullable();
        });

        Schema::create('konfirmasi_penerimaan', function ($table) {
            $table->id('konfirmasi_id');
            $table->bigInteger('surat_jalan_id');
            $table->bigInteger('kepala_shift_id');
            $table->timestamp('tanggal_konfirmasi')->nullable();
            $table->text('kondisi')->nullable();
        });

        Schema::create('stock_movements', function ($table) {
            $table->id('movement_id');
            $table->bigInteger('bahan_id');
            $table->bigInteger('cabang_id')->nullable();
            $table->bigInteger('dc_id')->nullable();
            $table->string('jenis_mutasi', 30);
            $table->decimal('jumlah', 14, 3);
            $table->bigInteger('referensi_id')->nullable();
            $table->timestamp('tanggal')->nullable();
            $table->string('keterangan')->nullable();
            $table->uuid('kelompok_id')->nullable();
        });
    }

    private function makeUser(array $overrides = []): Pengguna
    {
        return Pengguna::query()->create(array_merge([
            'nama_lengkap' => 'Budi Santoso',
            'username' => 'budi.ic1',
            'email' => 'budi.ic1@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.ic.db'),
            'cabang_id' => 1,
            'status' => 'aktif',
        ], $overrides));
    }

    private function makeBahan(float $isiPerKemasan = 700): BahanBaku
    {
        return BahanBaku::query()->create([
            'nama_bahan' => 'Susu UHT',
            'satuan' => 'ml',
            'nama_kemasan' => 'Kotak',
            'isi_per_kemasan' => $isiPerKemasan,
            'harga_beli' => 15000,
            'kategori' => 'minuman',
        ]);
    }

    /**
     * @param  array<int, int>  $qtyPerBahan  [bahan_id => qty_dikirim]
     */
    private function makeSuratJalan(string $status, array $qtyPerBahan, int $cabangId = 1): SuratJalan
    {
        $sj = SuratJalan::query()->create([
            'cabang_id' => $cabangId,
            'dc_id' => 1,
            'diterbitkan_oleh' => null,
            'tanggal_kirim' => now(),
            'status' => $status,
        ]);

        foreach ($qtyPerBahan as $bahanId => $qty) {
            DetailSuratJalan::query()->create([
                'surat_jalan_id' => $sj->surat_jalan_id,
                'bahan_id' => $bahanId,
                'qty_dikirim' => $qty,
                'qty_diterima' => null,
            ]);
        }

        return $sj;
    }

    private function makeStok(int $bahanId, float $jumlah, int $cabangId = 1): StokCabang
    {
        return StokCabang::query()->create([
            'cabang_id' => $cabangId,
            'bahan_id' => $bahanId,
            'jumlah_stok' => $jumlah,
            'stok_minimum' => 10,
        ]);
    }

    /* =========================================================
       ALUR BERHASIL
    ========================================================= */

    public function test_full_receiving_is_confirmed_and_stock_increases(): void
    {
        $user = $this->makeUser();
        $bahanA = $this->makeBahan(700);
        $bahanB = $this->makeBahan(1);
        $sj = $this->makeSuratJalan('dikirim', [
            $bahanA->bahan_id => 10,
            $bahanB->bahan_id => 50,
        ]);
        $this->makeStok($bahanA->bahan_id, 5);
        $this->makeStok($bahanB->bahan_id, 20);

        $response = $this->actingAs($user)->postJson(
            route('ic.receiving.confirm', $sj->surat_jalan_id),
            [
                'items' => [
                    ['bahan_id' => $bahanA->bahan_id, 'qty_diterima' => 10, 'catatan' => null],
                    ['bahan_id' => $bahanB->bahan_id, 'qty_diterima' => 50, 'catatan' => null],
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('status', 'diterima')
            ->assertJsonPath('surat_jalan_id', $sj->surat_jalan_id);

        $this->assertSame('selesai', $sj->fresh()->status);

        $konfirmasi = KonfirmasiPenerimaan::query()
            ->where('surat_jalan_id', $sj->surat_jalan_id)
            ->first();
        $this->assertNotNull($konfirmasi);
        $this->assertSame($user->user_id, $konfirmasi->kepala_shift_id);
        $this->assertSame('baik', $konfirmasi->kondisi);

        // qty_diterima tersimpan per detail.
        $this->assertEquals(10, (float) DetailSuratJalan::query()
            ->where('surat_jalan_id', $sj->surat_jalan_id)
            ->where('bahan_id', $bahanA->bahan_id)
            ->value('qty_diterima'));

        // Stok bertambah sesuai qty x isi kemasan.
        $this->assertEquals(5 + 10 * 700, (float) StokCabang::query()
            ->where('cabang_id', 1)
            ->where('bahan_id', $bahanA->bahan_id)
            ->value('jumlah_stok'));
        $this->assertEquals(20 + 50, (float) StokCabang::query()
            ->where('cabang_id', 1)
            ->where('bahan_id', $bahanB->bahan_id)
            ->value('jumlah_stok'));

        // Mutasi stok tercatat dan menunjuk surat jalan.
        $this->assertDatabaseHas('stock_movements', [
            'bahan_id' => $bahanA->bahan_id,
            'cabang_id' => 1,
            'dc_id' => null,
            'jenis_mutasi' => 'penerimaan_cabang',
            'referensi_id' => $sj->surat_jalan_id,
        ]);
        $this->assertSame(2, StockMovement::query()
            ->where('jenis_mutasi', 'penerimaan_cabang')
            ->where('referensi_id', $sj->surat_jalan_id)
            ->count());
    }

    public function test_receiving_with_shortage_is_confirmed_with_note(): void
    {
        $user = $this->makeUser();
        $bahan = $this->makeBahan(700);
        $sj = $this->makeSuratJalan('dikirim', [$bahan->bahan_id => 10]);
        $this->makeStok($bahan->bahan_id, 0);

        $this->actingAs($user)->postJson(
            route('ic.receiving.confirm', $sj->surat_jalan_id),
            [
                'items' => [
                    ['bahan_id' => $bahan->bahan_id, 'qty_diterima' => 8, 'catatan' => '2 kotak rusak'],
                ],
            ]
        )->assertOk();

        $this->assertSame('selesai', $sj->fresh()->status);
        $this->assertEquals(8 * 700, (float) StokCabang::query()
            ->where('bahan_id', $bahan->bahan_id)
            ->value('jumlah_stok'));
        $this->assertStringContainsString(
            '2 kotak rusak',
            KonfirmasiPenerimaan::query()->where('surat_jalan_id', $sj->surat_jalan_id)->value('kondisi')
        );
    }

    /* =========================================================
       KASUS PENOLAKAN
    ========================================================= */

    public function test_confirm_rejects_surat_jalan_not_yet_dikirim(): void
    {
        $user = $this->makeUser();
        $bahan = $this->makeBahan(700);
        $sj = $this->makeSuratJalan('disiapkan', [$bahan->bahan_id => 10]);
        $this->makeStok($bahan->bahan_id, 5);

        $this->actingAs($user)->postJson(
            route('ic.receiving.confirm', $sj->surat_jalan_id),
            [
                'items' => [
                    ['bahan_id' => $bahan->bahan_id, 'qty_diterima' => 10],
                ],
            ]
        )->assertStatus(422);

        $this->assertSame('disiapkan', $sj->fresh()->status);
        $this->assertDatabaseMissing('konfirmasi_penerimaan', ['surat_jalan_id' => $sj->surat_jalan_id]);
        $this->assertEquals(5, (float) StokCabang::query()->where('bahan_id', $bahan->bahan_id)->value('jumlah_stok'));
    }

    public function test_confirm_rejects_partial_items(): void
    {
        $user = $this->makeUser();
        $bahanA = $this->makeBahan(700);
        $bahanB = $this->makeBahan(1);
        $sj = $this->makeSuratJalan('dikirim', [
            $bahanA->bahan_id => 10,
            $bahanB->bahan_id => 50,
        ]);
        $this->makeStok($bahanA->bahan_id, 0);
        $this->makeStok($bahanB->bahan_id, 0);

        $this->actingAs($user)->postJson(
            route('ic.receiving.confirm', $sj->surat_jalan_id),
            [
                'items' => [
                    ['bahan_id' => $bahanA->bahan_id, 'qty_diterima' => 10],
                ],
            ]
        )->assertStatus(422);

        $this->assertSame('dikirim', $sj->fresh()->status);
        $this->assertDatabaseMissing('konfirmasi_penerimaan', ['surat_jalan_id' => $sj->surat_jalan_id]);
        $this->assertEquals(0, (float) StokCabang::query()->where('bahan_id', $bahanA->bahan_id)->value('jumlah_stok'));
        $this->assertNull(DetailSuratJalan::query()
            ->where('surat_jalan_id', $sj->surat_jalan_id)
            ->where('bahan_id', $bahanA->bahan_id)
            ->value('qty_diterima'));
    }

    public function test_confirm_rejects_duplicate_items(): void
    {
        $user = $this->makeUser();
        $bahan = $this->makeBahan(700);
        $sj = $this->makeSuratJalan('dikirim', [$bahan->bahan_id => 10]);
        $this->makeStok($bahan->bahan_id, 0);

        $this->actingAs($user)->postJson(
            route('ic.receiving.confirm', $sj->surat_jalan_id),
            [
                'items' => [
                    ['bahan_id' => $bahan->bahan_id, 'qty_diterima' => 6],
                    ['bahan_id' => $bahan->bahan_id, 'qty_diterima' => 4],
                ],
            ]
        )->assertStatus(422);

        $this->assertSame('dikirim', $sj->fresh()->status);
        $this->assertDatabaseMissing('konfirmasi_penerimaan', ['surat_jalan_id' => $sj->surat_jalan_id]);
        $this->assertEquals(0, (float) StokCabang::query()->where('bahan_id', $bahan->bahan_id)->value('jumlah_stok'));
        $this->assertDatabaseMissing('stock_movements', ['referensi_id' => $sj->surat_jalan_id]);
    }

    public function test_confirm_rejects_qty_received_exceeding_qty_shipped(): void
    {
        $user = $this->makeUser();
        $bahan = $this->makeBahan(700);
        $sj = $this->makeSuratJalan('dikirim', [$bahan->bahan_id => 10]);
        $this->makeStok($bahan->bahan_id, 0);

        $this->actingAs($user)->postJson(
            route('ic.receiving.confirm', $sj->surat_jalan_id),
            [
                'items' => [
                    ['bahan_id' => $bahan->bahan_id, 'qty_diterima' => 11],
                ],
            ]
        )->assertStatus(422);

        $this->assertSame('dikirim', $sj->fresh()->status);
        $this->assertDatabaseMissing('konfirmasi_penerimaan', ['surat_jalan_id' => $sj->surat_jalan_id]);
        $this->assertNull(DetailSuratJalan::query()
            ->where('surat_jalan_id', $sj->surat_jalan_id)
            ->where('bahan_id', $bahan->bahan_id)
            ->value('qty_diterima'));
        $this->assertEquals(0, (float) StokCabang::query()->where('bahan_id', $bahan->bahan_id)->value('jumlah_stok'));
    }

    public function test_confirm_rejects_items_not_on_surat_jalan(): void
    {
        $user = $this->makeUser();
        $bahan = $this->makeBahan(700);
        $lain = $this->makeBahan(1);
        $sj = $this->makeSuratJalan('dikirim', [$bahan->bahan_id => 10]);
        $this->makeStok($bahan->bahan_id, 0);
        $this->makeStok($lain->bahan_id, 0);

        $this->actingAs($user)->postJson(
            route('ic.receiving.confirm', $sj->surat_jalan_id),
            [
                'items' => [
                    ['bahan_id' => $bahan->bahan_id, 'qty_diterima' => 10],
                    ['bahan_id' => $lain->bahan_id, 'qty_diterima' => 5],
                ],
            ]
        )->assertStatus(422);

        $this->assertSame('dikirim', $sj->fresh()->status);
        $this->assertDatabaseMissing('konfirmasi_penerimaan', ['surat_jalan_id' => $sj->surat_jalan_id]);
    }

    public function test_confirm_cannot_run_twice(): void
    {
        $user = $this->makeUser();
        $bahan = $this->makeBahan(700);
        $sj = $this->makeSuratJalan('dikirim', [$bahan->bahan_id => 10]);
        $this->makeStok($bahan->bahan_id, 0);

        $payload = [
            'items' => [
                ['bahan_id' => $bahan->bahan_id, 'qty_diterima' => 10],
            ],
        ];

        $this->actingAs($user)
            ->postJson(route('ic.receiving.confirm', $sj->surat_jalan_id), $payload)
            ->assertOk();

        $this->actingAs($user)
            ->postJson(route('ic.receiving.confirm', $sj->surat_jalan_id), $payload)
            ->assertStatus(422);

        // Stok hanya bertambah satu kali.
        $this->assertEquals(10 * 700, (float) StokCabang::query()
            ->where('bahan_id', $bahan->bahan_id)
            ->value('jumlah_stok'));
        $this->assertSame(1, StockMovement::query()->where('referensi_id', $sj->surat_jalan_id)->count());
    }

    public function test_confirm_rejects_surat_jalan_of_other_branch(): void
    {
        $user = $this->makeUser(['cabang_id' => 1]);
        $bahan = $this->makeBahan(700);
        $sj = $this->makeSuratJalan('dikirim', [$bahan->bahan_id => 10], cabangId: 2);

        $this->actingAs($user)->postJson(
            route('ic.receiving.confirm', $sj->surat_jalan_id),
            [
                'items' => [
                    ['bahan_id' => $bahan->bahan_id, 'qty_diterima' => 10],
                ],
            ]
        )->assertNotFound();

        $this->assertDatabaseMissing('konfirmasi_penerimaan', ['surat_jalan_id' => $sj->surat_jalan_id]);
    }
}
