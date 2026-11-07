<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\Dc;
use App\Models\DetailSuratJalan;
use App\Models\KonfirmasiPenerimaan;
use App\Models\Pengguna;
use App\Models\StockMovement;
use App\Models\StokCabang;
use App\Models\SuratJalan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReceivingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cabang::query()->firstOrCreate(
            ['cabang_id' => 1],
            ['nama_cabang' => 'Store 1', 'alamat' => 'Alamat']
        );
        Cabang::query()->firstOrCreate(
            ['cabang_id' => 2],
            ['nama_cabang' => 'Store 2', 'alamat' => 'Alamat']
        );
        Dc::query()->firstOrCreate(
            ['dc_id' => 1],
            ['nama_dc' => 'Distribution Center', 'alamat' => 'Alamat']
        );
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
