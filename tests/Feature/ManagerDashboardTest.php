<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\DetailTransaksi;
use App\Models\DraftInstruksi;
use App\Models\KategoriMenu;
use App\Models\MenuProduk;
use App\Models\Pengguna;
use App\Models\ResepMenu;
use App\Models\StokCabang;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use App\Models\TransaksiPenjualan;
use App\Models\VarianMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagerDashboardTest extends TestCase
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

        Schema::create('cabang', function ($table) {
            $table->id('cabang_id');
            $table->string('nama_cabang', 100);
            $table->string('alamat')->nullable();
            $table->string('telepon', 20)->nullable();
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

        Schema::create('kategori_menu', function ($table) {
            $table->id('kategori_menu_id');
            $table->string('nama_kategori', 100);
        });

        Schema::create('menu_produk', function ($table) {
            $table->id('menu_id');
            $table->bigInteger('kategori_menu_id')->nullable();
            $table->string('nama_menu', 100);
            $table->decimal('harga_jual', 12, 2)->default(0);
            $table->string('emoji', 20)->nullable();
        });

        Schema::create('varian_menu', function ($table) {
            $table->id('varian_id');
            $table->bigInteger('menu_id');
            $table->string('nama_varian', 50);
            $table->decimal('harga_tambahan', 12, 2)->default(0);
        });

        Schema::create('resep_menu', function ($table) {
            $table->id('resep_id');
            $table->bigInteger('varian_id');
            $table->bigInteger('bahan_id');
            $table->decimal('jumlah_bahan', 14, 3);
        });

        Schema::create('transaksi_penjualan', function ($table) {
            $table->id('transaksi_id');
            $table->bigInteger('cabang_id');
            $table->bigInteger('kasir_id')->nullable();
            $table->timestamp('tanggal')->nullable();
            $table->decimal('total_bayar', 14, 2)->default(0);
            $table->string('status', 20);
            $table->string('alasan_batal')->nullable();
        });

        Schema::create('detail_transaksi', function ($table) {
            $table->id('detail_id');
            $table->bigInteger('transaksi_id');
            $table->bigInteger('varian_id');
            $table->integer('qty');
            $table->decimal('harga_satuan', 14, 2);
            $table->decimal('subtotal', 14, 2);
        });

        Schema::create('payments', function ($table) {
            $table->id('payment_id');
            $table->bigInteger('transaksi_id');
            $table->string('metode', 30);
            $table->string('status', 30)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('jumlah_diterima', 14, 2)->nullable();
            $table->decimal('kembalian', 14, 2)->nullable();
        });

        Schema::create('waste_log', function ($table) {
            $table->id('waste_id');
            $table->bigInteger('cabang_id');
            $table->bigInteger('bahan_id');
            $table->bigInteger('kepala_shift_id')->nullable();
            $table->decimal('qty', 14, 3);
            $table->string('alasan');
            $table->timestamp('tanggal')->nullable();
            $table->uuid('kelompok_id')->nullable();
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

        Schema::create('draft_instruksi', function ($table) {
            $table->id('draft_id');
            $table->bigInteger('cabang_id');
            $table->bigInteger('dibuat_oleh')->nullable();
            $table->string('asal', 30)->nullable();
            $table->string('status', 30);
            $table->timestamp('dibuat_pada')->nullable();
            $table->bigInteger('disetujui_oleh')->nullable();
            $table->timestamp('tanggal_persetujuan')->nullable();
        });

        $this->seedData();
    }

    private function seedData(): void
    {
        Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);

        Pengguna::query()->create([
            'nama_lengkap' => 'Dewi Manager',
            'username' => 'dewi.manager1',
            'email' => 'dewi.manager1@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.manager.db'),
            'cabang_id' => 1,
            'status' => 'aktif',
        ]);

        Pengguna::query()->create([
            'nama_lengkap' => 'Kasir Satu',
            'username' => 'kasir.satu',
            'email' => 'kasir.satu@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.kasir.db'),
            'cabang_id' => 1,
            'status' => 'aktif',
        ]);

        // Bahan dengan nama khas supaya bisa dikenali di halaman
        $bahan = BahanBaku::query()->create([
            'nama_bahan' => 'Kopi Uji Dashboard',
            'satuan' => 'g',
            'nama_kemasan' => 'Kg',
            'isi_per_kemasan' => 1000,
            'harga_beli' => 120000,
            'kategori' => 'bahan utama',
        ]);

        StokCabang::query()->create([
            'cabang_id' => 1,
            'bahan_id' => $bahan->bahan_id,
            'jumlah_stok' => 3,
            'stok_minimum' => 10,
        ]);

        $kategori = KategoriMenu::query()->create(['nama_kategori' => 'Kopi']);

        $menu = MenuProduk::query()->create([
            'kategori_menu_id' => $kategori->kategori_menu_id,
            'nama_menu' => 'Menu Uji',
            'harga_jual' => 20000,
            'emoji' => '☕',
        ]);

        $varian = VarianMenu::query()->create([
            'menu_id' => $menu->menu_id,
            'nama_varian' => 'Regular',
        ]);

        ResepMenu::query()->create([
            'varian_id' => $varian->varian_id,
            'bahan_id' => $bahan->bahan_id,
            'jumlah_bahan' => 30,
        ]);

        $kasir = Pengguna::query()->where('username', 'kasir.satu')->firstOrFail();

        $trx = TransaksiPenjualan::query()->create([
            'cabang_id' => 1,
            'kasir_id' => $kasir->user_id,
            'tanggal' => now(),
            'total_bayar' => 40000,
            'status' => 'berhasil',
        ]);

        DetailTransaksi::query()->create([
            'transaksi_id' => $trx->transaksi_id,
            'varian_id' => $varian->varian_id,
            'qty' => 2,
            'harga_satuan' => 20000,
            'subtotal' => 40000,
        ]);

        StockMovement::query()->create([
            'bahan_id' => $bahan->bahan_id,
            'cabang_id' => 1,
            'jenis_mutasi' => 'waste',
            'jumlah' => 5,
            'tanggal' => now(),
            'keterangan' => 'Waste · Kedaluwarsa',
        ]);

        SuratJalan::query()->create([
            'cabang_id' => 1,
            'dc_id' => 1,
            'tanggal_kirim' => now(),
            'status' => 'dikirim',
        ]);

        DraftInstruksi::query()->create([
            'cabang_id' => 1,
            'asal' => 'otomatis',
            'status' => 'menunggu',
            'dibuat_pada' => now(),
        ]);
    }

    private function manager(): Pengguna
    {
        return Pengguna::query()->where('username', 'dewi.manager1')->firstOrFail();
    }

    /**
     * Ekstrak payload window.KOPSYNC dari HTML (JSON escape aman).
     *
     * @return array<string, mixed>
     */
    private function payload($response): array
    {
        $html = $response->getContent();

        $this->assertTrue(
            str_contains($html, 'window.KOPSYNC'),
            'Tag window.KOPSYNC tidak ditemukan di halaman dashboard.'
        );

        preg_match('/window\.KOPSYNC = ([\s\S]*?);\s*<\/script>/', $html, $matches);

        $this->assertNotEmpty($matches, 'Payload window.KOPSYNC gagal diekstrak dari halaman.');

        $payload = json_decode($matches[1], true);

        $this->assertIsArray($payload, 'Payload window.KOPSYNC bukan JSON valid.');

        return $payload;
    }

    public function test_dashboard_is_rendered_from_database_payload(): void
    {
        $response = $this->actingAs($this->manager())
            ->get(route('manager.dashboard'))
            ->assertOk();

        // Nama cabang dari tabel cabang, bukan config demo
        $response->assertSee('Malang · Alun-Alun');

        $payload = $this->payload($response);

        $this->assertArrayHasKey('summary', $payload);
        $this->assertArrayHasKey('draftNo', $payload);
        $this->assertSame('#RS-0001', $payload['draftNo']);

        $itemNames = array_column($payload['items'], 'name');
        $this->assertContains('Kopi Uji Dashboard', $itemNames);
    }

    public function test_alert_page_shows_low_stock_and_waste_from_database(): void
    {
        $this->actingAs($this->manager())
            ->get(route('manager.alert'))
            ->assertOk()
            ->assertSee('Kopi Uji Dashboard', false)
            ->assertSee('Kedaluwarsa', false)
            ->assertSee('#RS-0001', false);
    }

    public function test_dashboard_omzet_summary_matches_transactions(): void
    {
        $payload = $this->payload(
            $this->actingAs($this->manager())->get(route('manager.dashboard'))->assertOk()
        );

        // 1 transaksi Rp40.000 + stok menipis + 1 surat jalan dikirim
        $this->assertEqualsWithDelta(40000, $payload['summary']['omzet'], 0.01);
        $this->assertSame(1, $payload['summary']['dc_pending']);
        $this->assertSame(1, $payload['summary']['low_count']);
        $this->assertSame('waste', $payload['mutations'][0]['type']);
        $this->assertSame(1, count($payload['shipments']));
    }

    public function test_wrong_role_cannot_open_manager_pages(): void
    {
        $kasir = Pengguna::query()->where('username', 'kasir.satu')->firstOrFail();

        $this->actingAs($kasir)->get(route('manager.dashboard'))->assertForbidden();
        $this->actingAs($kasir)->get(route('manager.alert'))->assertForbidden();
    }
}
