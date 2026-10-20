<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\DetailTransaksi;
use App\Models\KategoriMenu;
use App\Models\MenuProduk;
use App\Models\ResepMenu;
use App\Models\StokCabang;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use App\Models\TransaksiPenjualan;
use App\Models\VarianMenu;
use App\Models\WasteLog;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $cabangId = 1;

    private int $bahanKopiId = 0;

    private int $bahanSusuId = 0;

    private int $varianId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Skema minimal untuk sqlite in-memory (tabel domain asli ada di
        // Supabase dan tidak dimigrasikan lewat Laravel migrations).
        Schema::create('bahan_baku', function ($table) {
            $table->id('bahan_id');
            $table->string('nama_bahan', 100);
            $table->string('satuan', 20);
            $table->decimal('harga_beli', 12, 2)->default(0);
            $table->string('nama_kemasan', 50)->nullable();
            $table->decimal('isi_per_kemasan', 12, 3)->default(1);
            $table->string('kategori', 50)->nullable();
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

        $this->seedCatalog();
        $this->seedTransactions();
        $this->seedWasteAndStock();
    }

    private function seedCatalog(): void
    {
        // Kopi: Rp120.000 per 1000 g => Rp120 / g
        $this->bahanKopiId = BahanBaku::query()->create([
            'nama_bahan' => 'Kopi Arabika',
            'satuan' => 'g',
            'nama_kemasan' => 'Kg',
            'isi_per_kemasan' => 1000,
            'harga_beli' => 120000,
            'kategori' => 'bahan utama',
        ])->bahan_id;

        // Susu: Rp20.000 per 1000 ml => Rp20 / ml
        $this->bahanSusuId = BahanBaku::query()->create([
            'nama_bahan' => 'Susu UHT',
            'satuan' => 'ml',
            'nama_kemasan' => 'Liter',
            'isi_per_kemasan' => 1000,
            'harga_beli' => 20000,
            'kategori' => 'bahan utama',
        ])->bahan_id;

        $kategoriId = KategoriMenu::query()->create(['nama_kategori' => 'Kopi'])->kategori_menu_id;

        $menuId = MenuProduk::query()->create([
            'kategori_menu_id' => $kategoriId,
            'nama_menu' => 'Latte',
            'harga_jual' => 25000,
            'emoji' => '☕',
        ])->menu_id;

        $this->varianId = VarianMenu::query()->create([
            'menu_id' => $menuId,
            'nama_varian' => 'Regular',
            'harga_tambahan' => 0,
        ])->varian_id;

        // HPP per porsi = 30 g × 120 + 200 ml × 20 = 3.600 + 4.000 = 7.600
        ResepMenu::query()->create([
            'varian_id' => $this->varianId,
            'bahan_id' => $this->bahanKopiId,
            'jumlah_bahan' => 30,
        ]);

        ResepMenu::query()->create([
            'varian_id' => $this->varianId,
            'bahan_id' => $this->bahanSusuId,
            'jumlah_bahan' => 200,
        ]);
    }

    private function seedTransactions(): void
    {
        // Kemarin: 1 transaksi Rp40.000 (pembanding delta)
        $yesterday = now()->subDay();

        $trxKemarin = TransaksiPenjualan::query()->create([
            'cabang_id' => $this->cabangId,
            'tanggal' => $yesterday,
            'total_bayar' => 40000,
            'status' => 'berhasil',
        ]);

        DetailTransaksi::query()->create([
            'transaksi_id' => $trxKemarin->transaksi_id,
            'varian_id' => $this->varianId,
            'qty' => 1,
            'harga_satuan' => 40000,
            'subtotal' => 40000,
        ]);

        // Hari ini: 2 transaksi berhasil (qty 2 + qty 1) = omzet Rp120.000
        foreach ([[2, 80000], [1, 40000]] as [$qty, $subtotal]) {
            $trx = TransaksiPenjualan::query()->create([
                'cabang_id' => $this->cabangId,
                'tanggal' => now(),
                'total_bayar' => $subtotal,
                'status' => 'berhasil',
            ]);

            DetailTransaksi::query()->create([
                'transaksi_id' => $trx->transaksi_id,
                'varian_id' => $this->varianId,
                'qty' => $qty,
                'harga_satuan' => (int) ($subtotal / $qty),
                'subtotal' => $subtotal,
            ]);
        }

        // Transaksi batal tidak boleh masuk hitungan
        $trxBatal = TransaksiPenjualan::query()->create([
            'cabang_id' => $this->cabangId,
            'tanggal' => now(),
            'total_bayar' => 99999,
            'status' => 'batal',
            'alasan_batal' => 'Uji coba',
        ]);

        DetailTransaksi::query()->create([
            'transaksi_id' => $trxBatal->transaksi_id,
            'varian_id' => $this->varianId,
            'qty' => 9,
            'harga_satuan' => 11111,
            'subtotal' => 99999,
        ]);
    }

    private function seedWasteAndStock(): void
    {
        // Waste 10 g kopi hari ini => 10 × Rp120 = Rp1.200
        WasteLog::query()->create([
            'cabang_id' => $this->cabangId,
            'bahan_id' => $this->bahanKopiId,
            'qty' => 10,
            'alasan' => 'Kedaluwarsa',
            'tanggal' => now(),
        ]);

        // Mutasi stok: masuk 5, keluar 3, waste 10
        foreach ([['penerimaan_cabang', 5], ['penjualan', 3], ['waste', 10]] as [$jenis, $jumlah]) {
            StockMovement::query()->create([
                'bahan_id' => $this->bahanKopiId,
                'cabang_id' => $this->cabangId,
                'jenis_mutasi' => $jenis,
                'jumlah' => $jumlah,
                'tanggal' => now(),
                'keterangan' => 'Uji ' . $jenis,
            ]);
        }

        // Stok kopi di bawah minimum, susu aman
        StokCabang::query()->create([
            'cabang_id' => $this->cabangId,
            'bahan_id' => $this->bahanKopiId,
            'jumlah_stok' => 4,
            'stok_minimum' => 10,
        ]);

        StokCabang::query()->create([
            'cabang_id' => $this->cabangId,
            'bahan_id' => $this->bahanSusuId,
            'jumlah_stok' => 100,
            'stok_minimum' => 10,
        ]);

        // 1 surat jalan dikirim, menunggu konfirmasi IC
        SuratJalan::query()->create([
            'cabang_id' => $this->cabangId,
            'dc_id' => 1,
            'tanggal_kirim' => now(),
            'status' => 'dikirim',
        ]);
    }

    private function service(): ReportService
    {
        return app(ReportService::class);
    }

    public function test_unit_costs_are_derived_from_price_per_package(): void
    {
        $costs = $this->service()->unitCosts();

        $this->assertSame(120.0, $costs[$this->bahanKopiId]);
        $this->assertSame(20.0, $costs[$this->bahanSusuId]);
    }

    public function test_portion_cost_combines_recipe_and_unit_costs(): void
    {
        $costs = $this->service()->portionCosts();

        $this->assertSame(7600.0, $costs[$this->varianId]);
    }

    public function test_daily_profit_counts_only_successful_transactions(): void
    {
        $rows = $this->service()->dailyProfit($this->cabangId);
        $today = collect($rows)->firstWhere('date', now()->toDateString());
        $yesterday = collect($rows)->firstWhere('date', now()->subDay()->toDateString());

        $this->assertNotNull($today);
        $this->assertSame(2, $today['count']);
        $this->assertEqualsWithDelta(120000.0, $today['omzet'], 0.01);
        $this->assertEqualsWithDelta(22800.0, $today['hpp'], 0.01); // 3 porsi × 7.600
        $this->assertEqualsWithDelta(1200.0, $today['waste'], 0.01);
        $this->assertEqualsWithDelta(96000.0, $today['laba'], 0.01);
        $this->assertEqualsWithDelta(80.0, $today['margin'], 0.01);

        $this->assertNotNull($yesterday);
        $this->assertEqualsWithDelta(40000.0, $yesterday['omzet'], 0.01);
    }

    public function test_menu_sales_aggregates_by_menu(): void
    {
        $rows = $this->service()->menuSales($this->cabangId, now()->toDateString(), now()->toDateString());

        $this->assertCount(1, $rows);
        $this->assertSame('Latte', $rows[0]['name']);
        $this->assertSame(3, $rows[0]['qty']);
        $this->assertEqualsWithDelta(120000.0, $rows[0]['omzet'], 0.01);
        $this->assertEqualsWithDelta(22800.0, $rows[0]['hpp'], 0.01);
        $this->assertEqualsWithDelta(97200.0, $rows[0]['laba'], 0.01);

        // Tanpa rentang tanggal ikut termasuk transaksi kemarin (qty 1)
        $this->assertSame(4, $this->service()->menuSales($this->cabangId)[0]['qty']);
    }

    public function test_stock_mutations_split_incoming_outgoing_and_waste(): void
    {
        $rows = $this->service()->stockMutations($this->cabangId);

        $this->assertCount(1, $rows);
        $this->assertSame('Kopi Arabika', $rows[0]['name']);
        $this->assertEqualsWithDelta(5.0, $rows[0]['masuk'], 0.01);
        $this->assertEqualsWithDelta(3.0, $rows[0]['keluar'], 0.01);
        $this->assertEqualsWithDelta(10.0, $rows[0]['waste'], 0.01);
    }

    public function test_branch_summary_reports_today_low_stock_and_dc_pending(): void
    {
        $summary = $this->service()->branchSummary($this->cabangId);

        $this->assertSame(now()->toDateString(), $summary['date']);
        $this->assertEqualsWithDelta(120000.0, $summary['omzet'], 0.01);
        $this->assertEqualsWithDelta(1200.0, $summary['waste'], 0.01);
        $this->assertSame(1, $summary['waste_count']);
        $this->assertEqualsWithDelta(200.0, $summary['delta_pct'], 0.01); // 120rb vs 40rb kemarin
        $this->assertSame(1, $summary['low_count']);
        $this->assertSame(0, $summary['low_habis']);
        $this->assertSame(1, $summary['dc_pending']);
        $this->assertSame(1, $summary['dc_sent']);
    }

    public function test_null_branch_aggregates_all_branches(): void
    {
        $rows = $this->service()->dailyProfit(null);
        $today = collect($rows)->firstWhere('date', now()->toDateString());

        $this->assertEqualsWithDelta(120000.0, $today['omzet'], 0.01);
        $this->assertEqualsWithDelta(1200.0, $today['waste'], 0.01);
    }
}
