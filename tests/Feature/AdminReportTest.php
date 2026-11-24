<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\DetailTransaksi;
use App\Models\KategoriMenu;
use App\Models\MenuProduk;
use App\Models\Pengguna;
use App\Models\ResepMenu;
use App\Models\StockMovement;
use App\Models\TransaksiPenjualan;
use App\Models\VarianMenu;
use App\Models\WasteLog;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_report(): void
    {
        $branch = Cabang::query()->create(['nama_cabang' => 'Cabang Satu', 'alamat' => 'Jl. 1']);

        foreach (['kasir', 'manager'] as $roleKey) {
            $user = Pengguna::query()->create([
                'nama_lengkap' => 'User '.$roleKey,
                'username' => 'user.'.$roleKey,
                'email' => $roleKey.'@kopsync.id',
                'password_hash' => Hash::make('password123'),
                'role' => config("menu.roles.{$roleKey}.db"),
                'cabang_id' => $branch->cabang_id,
                'status' => 'aktif',
            ]);

            $this->actingAs($user)->get(route('admin.report'))->assertForbidden();
        }
    }

    public function test_admin_report_displays_branch_comparison_and_filters_by_branch(): void
    {
        $admin = $this->makeAdmin();
        $branch1 = Cabang::query()->create(['nama_cabang' => 'Cabang Satu', 'alamat' => 'Jl. 1']);
        $branch2 = Cabang::query()->create(['nama_cabang' => 'Cabang Dua', 'alamat' => 'Jl. 2']);

        $bahan = BahanBaku::query()->create([
            'nama_bahan' => 'Kopi Blend',
            'kategori' => 'Kopi',
            'satuan' => 'g',
            'nama_kemasan' => 'Kg',
            'isi_per_kemasan' => 1000,
            'harga_beli' => 100000,
            'minimum_order_qty' => 1,
        ]);
        $kategori = KategoriMenu::query()->create(['nama_kategori' => 'Kopi']);
        $menu = MenuProduk::query()->create([
            'kategori_menu_id' => $kategori->kategori_menu_id,
            'nama_menu' => 'Espresso',
            'harga_jual' => 20000,
        ]);
        $varian = VarianMenu::query()->create([
            'menu_id' => $menu->menu_id,
            'nama_varian' => 'Regular',
            'harga_tambahan' => 0,
        ]);
        ResepMenu::query()->create([
            'varian_id' => $varian->varian_id,
            'bahan_id' => $bahan->bahan_id,
            'jumlah_bahan' => 20,
        ]);

        // Cabang Satu: 1 berhasil (2x Rp20.000 = 40.000), HPP = 2 * (20 * 100) = 4.000
        $trx1 = TransaksiPenjualan::query()->create([
            'cabang_id' => $branch1->cabang_id,
            'tanggal' => now(),
            'total_bayar' => 40000,
            'status' => 'berhasil',
        ]);
        DetailTransaksi::query()->create([
            'transaksi_id' => $trx1->transaksi_id,
            'varian_id' => $varian->varian_id,
            'qty' => 2,
            'harga_satuan' => 20000,
            'subtotal' => 40000,
        ]);

        // Transaksi batal di Cabang Satu harus diabaikan
        $trxBatal = TransaksiPenjualan::query()->create([
            'cabang_id' => $branch1->cabang_id,
            'tanggal' => now(),
            'total_bayar' => 99000,
            'status' => 'batal',
        ]);
        DetailTransaksi::query()->create([
            'transaksi_id' => $trxBatal->transaksi_id,
            'varian_id' => $varian->varian_id,
            'qty' => 3,
            'harga_satuan' => 33000,
            'subtotal' => 99000,
        ]);

        WasteLog::query()->create([
            'cabang_id' => $branch1->cabang_id,
            'bahan_id' => $bahan->bahan_id,
            'qty' => 5,
            'alasan' => 'Tumpah',
            'tanggal' => now(),
        ]);
        StockMovement::query()->create([
            'cabang_id' => $branch1->cabang_id,
            'bahan_id' => $bahan->bahan_id,
            'jenis_mutasi' => 'penjualan',
            'jumlah' => 40,
            'tanggal' => now(),
        ]);

        // Cabang Dua: 1 berhasil (1x Rp20.000 = 20.000)
        $trx2 = TransaksiPenjualan::query()->create([
            'cabang_id' => $branch2->cabang_id,
            'tanggal' => now(),
            'total_bayar' => 20000,
            'status' => 'berhasil',
        ]);
        DetailTransaksi::query()->create([
            'transaksi_id' => $trx2->transaksi_id,
            'varian_id' => $varian->varian_id,
            'qty' => 1,
            'harga_satuan' => 20000,
            'subtotal' => 20000,
        ]);

        $comparison = app(ReportService::class)->branchComparison(
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
        );
        $this->assertCount(2, $comparison);
        $c1 = collect($comparison)->firstWhere('cabang_id', $branch1->cabang_id);
        $this->assertSame(40000.0, $c1['omzet']);
        $this->assertSame(4000.0, $c1['hpp']);
        $this->assertSame(500.0, $c1['waste']);
        $this->assertSame(35500.0, $c1['laba']);

        $this->actingAs($admin)
            ->get(route('admin.report', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Cabang Satu')
            ->assertSee('Cabang Dua')
            ->assertSee('Rp60.000');

        $this->actingAs($admin)
            ->get(route('admin.report', [
                'cabang_id' => $branch1->cabang_id,
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Cabang Satu')
            ->assertSee('Rp40.000')
            ->assertDontSee('Rp60.000');
    }

    public function test_invalid_cabang_id_and_invalid_date_range_are_handled_safely(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.report', ['cabang_id' => 'bukan-angka']))
            ->assertOk()
            ->assertSee('Semua Cabang');

        $this->actingAs($admin)
            ->from(route('admin.report'))
            ->get(route('admin.report', ['from' => '2026-11-20', 'to' => '2026-11-10']))
            ->assertRedirect(route('admin.report'))
            ->assertSessionHasErrors('to');
    }

    public function test_csv_export_outputs_utf8_bom_semicolon_data_and_sanitizes_formulas(): void
    {
        $admin = $this->makeAdmin();
        $branch = Cabang::query()->create(['nama_cabang' => '=SUM(1+1)', 'alamat' => 'Jl. CSV']);

        $trx = TransaksiPenjualan::query()->create([
            'cabang_id' => $branch->cabang_id,
            'tanggal' => now(),
            'total_bayar' => 25000,
            'status' => 'berhasil',
        ]);
        $kategori = KategoriMenu::query()->create(['nama_kategori' => 'Non Kopi']);
        $menu = MenuProduk::query()->create([
            'kategori_menu_id' => $kategori->kategori_menu_id,
            'nama_menu' => '+Matcha',
            'harga_jual' => 25000,
        ]);
        $varian = VarianMenu::query()->create([
            'menu_id' => $menu->menu_id,
            'nama_varian' => 'Regular',
            'harga_tambahan' => 0,
        ]);
        DetailTransaksi::query()->create([
            'transaksi_id' => $trx->transaksi_id,
            'varian_id' => $varian->varian_id,
            'qty' => 1,
            'harga_satuan' => 25000,
            'subtotal' => 25000,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.report', [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
                'export' => 1,
            ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=SUM(1+1);", $csv);
        $this->assertStringContainsString("'+Matcha;", $csv);
    }

    private function makeAdmin(): Pengguna
    {
        return Pengguna::query()->create([
            'nama_lengkap' => 'Admin Utama',
            'username' => 'admin.utama',
            'email' => 'admin@report.test',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.admin.db'),
            'status' => 'aktif',
        ]);
    }
}
