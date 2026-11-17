<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\Dc;
use App\Models\DetailTransaksi;
use App\Models\DraftInstruksi;
use App\Models\KategoriMenu;
use App\Models\MenuProduk;
use App\Models\Pengguna;
use App\Models\ResepMenu;
use App\Models\StockMovement;
use App\Models\StokCabang;
use App\Models\SuratJalan;
use App\Models\TransaksiPenjualan;
use App\Models\VarianMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedData();
    }

    private function seedData(): void
    {
        Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        Dc::query()->create(['dc_id' => 1, 'nama_dc' => 'Distribution Center', 'alamat' => 'Alamat']);

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
