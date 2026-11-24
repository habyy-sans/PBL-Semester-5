<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\Dc;
use App\Models\DetailDraftInstruksi;
use App\Models\DetailSuratJalan;
use App\Models\DraftInstruksi;
use App\Models\KategoriMenu;
use App\Models\MenuProduk;
use App\Models\NotifikasiAlert;
use App\Models\Pengguna;
use App\Models\ResepMenu;
use App\Models\StokCabang;
use App\Models\StokDc;
use App\Models\SuratJalan;
use App\Models\VarianMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_master_totals_and_low_stock(): void
    {
        $admin = $this->makeAdmin();
        $branch = Cabang::query()->create([
            'nama_cabang' => 'Cabang Malang',
            'alamat' => 'Jl. Merdeka',
        ]);
        $ingredient = $this->makeIngredient('Susu Segar');
        StokCabang::query()->create([
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $ingredient->bahan_id,
            'jumlah_stok' => 2,
            'stok_minimum' => 5,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Total Cabang')
            ->assertSee('Susu Segar')
            ->assertSee('Menipis');
    }

    public function test_dashboard_renders_action_needed_banners_for_incomplete_setup(): void
    {
        $admin = $this->makeAdmin();
        Cabang::query()->create([
            'nama_cabang' => 'Cabang Tanpa Manajer',
            'alamat' => 'Jl. Sudirman',
        ]);
        $category = KategoriMenu::query()->create(['nama_kategori' => 'Kopi']);
        MenuProduk::query()->create([
            'kategori_menu_id' => $category->kategori_menu_id,
            'nama_menu' => 'Americano Polos',
            'harga_jual' => 18000,
        ]);
        $menuWithVariant = MenuProduk::query()->create([
            'kategori_menu_id' => $category->kategori_menu_id,
            'nama_menu' => 'Latte Tanpa Resep',
            'harga_jual' => 25000,
        ]);
        VarianMenu::query()->create([
            'menu_id' => $menuWithVariant->menu_id,
            'nama_varian' => 'Regular',
            'harga_tambahan' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Perlu Perhatian Segera')
            ->assertSee('cabang belum memiliki manajer aktif')
            ->assertSee('menu belum memiliki varian')
            ->assertSee('varian belum memiliki resep');
    }

    public function test_admin_can_create_filter_and_update_master_data(): void
    {
        $this->actingAs($this->makeAdmin())
            ->post(route('admin.kategori-menu.store'), ['nama_kategori' => 'Kopi'])
            ->assertRedirect(route('admin.kategori-menu.index'));

        $category = KategoriMenu::query()->firstOrFail();

        $this->get(route('admin.kategori-menu.index', ['q' => 'kopi']))
            ->assertOk()
            ->assertSee('Kopi');

        $this->put(route('admin.kategori-menu.update', $category), ['nama_kategori' => 'Minuman'])
            ->assertRedirect(route('admin.kategori-menu.index'));

        $this->assertSame('Minuman', $category->fresh()->nama_kategori);
    }

    public function test_duplicate_menu_category_is_rejected_case_insensitively(): void
    {
        KategoriMenu::query()->create(['nama_kategori' => 'Kopi']);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin.kategori-menu.create'))
            ->post(route('admin.kategori-menu.store'), ['nama_kategori' => 'Kopi'])
            ->assertRedirect(route('admin.kategori-menu.create'))
            ->assertSessionHasErrors('nama_kategori');

        $this->actingAs($admin)
            ->from(route('admin.kategori-menu.create'))
            ->post(route('admin.kategori-menu.store'), ['nama_kategori' => 'kopi'])
            ->assertRedirect(route('admin.kategori-menu.create'))
            ->assertSessionHasErrors('nama_kategori');
    }

    public function test_admin_resource_forms_render_with_relationship_options(): void
    {
        $this->actingAs($this->makeAdmin());
        $category = KategoriMenu::query()->create(['nama_kategori' => 'Kopi']);
        $menu = MenuProduk::query()->create([
            'kategori_menu_id' => $category->kategori_menu_id,
            'nama_menu' => 'Latte',
            'harga_jual' => 25000,
        ]);
        VarianMenu::query()->create([
            'menu_id' => $menu->menu_id,
            'nama_varian' => 'Regular',
            'harga_tambahan' => 0,
        ]);
        $this->makeIngredient('Susu Segar');

        foreach ([
            'admin.cabang.create',
            'admin.dc.create',
            'admin.bahan-baku.create',
            'admin.kategori-menu.create',
            'admin.menu-produk.create',
            'admin.varian-menu.create',
            'admin.resep-menu.create',
            'admin.pengguna.create',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_ingredient_syncs_stock_enforces_moq_and_checks_dependents_on_delete(): void
    {
        $admin = $this->makeAdmin();
        $branch = Cabang::query()->create(['nama_cabang' => 'Cabang Barat', 'alamat' => 'Jl. Barat']);
        $dc = Dc::query()->create(['nama_dc' => 'DC Barat', 'alamat' => 'Jl. Gudang']);

        $this->actingAs($admin)
            ->post(route('admin.bahan-baku.store'), [
                'nama_bahan' => 'Kopi Arabika',
                'kategori' => 'Biji Kopi',
                'satuan' => 'g',
                'harga_beli' => 18000,
                'nama_kemasan' => 'Pack',
                'isi_per_kemasan' => 1000,
                'minimum_order_qty' => 3,
            ])
            ->assertRedirect(route('admin.bahan-baku.index'));

        $bahan = BahanBaku::query()->where('nama_bahan', 'Kopi Arabika')->firstOrFail();
        $this->assertSame(3, $bahan->minimum_order_qty);
        $this->assertDatabaseHas('stok_cabang', [
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $bahan->bahan_id,
            'jumlah_stok' => 0,
            'stok_minimum' => 0,
        ]);
        $this->assertDatabaseHas('stok_dc', [
            'dc_id' => $dc->dc_id,
            'bahan_id' => $bahan->bahan_id,
            'jumlah_stok' => 0,
            'stok_minimum' => 0,
        ]);

        // Duplikat case-insensitive ditolak
        $this->actingAs($admin)
            ->from(route('admin.bahan-baku.create'))
            ->post(route('admin.bahan-baku.store'), [
                'nama_bahan' => 'kopi arabika',
                'kategori' => 'Biji Kopi',
                'satuan' => 'g',
                'harga_beli' => 18500,
                'nama_kemasan' => 'Pack',
                'isi_per_kemasan' => 1000,
                'minimum_order_qty' => 2,
            ])
            ->assertRedirect(route('admin.bahan-baku.create'))
            ->assertSessionHasErrors('nama_bahan');

        // Hapus bahan dengan stok masih 0/0 dan tanpa riwayat diizinkan & memangkas baris 0/0
        $this->actingAs($admin)
            ->delete(route('admin.bahan-baku.destroy', $bahan))
            ->assertRedirect(route('admin.bahan-baku.index'));

        $this->assertDatabaseMissing('bahan_baku', ['bahan_id' => $bahan->bahan_id]);
        $this->assertDatabaseCount('stok_cabang', 0);
        $this->assertDatabaseCount('stok_dc', 0);

        // Bahan dengan stok aktif > 0 ditolak saat dihapus
        $activeBahan = $this->makeIngredient('Gula Aren');
        StokCabang::query()->create([
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $activeBahan->bahan_id,
            'jumlah_stok' => 5,
            'stok_minimum' => 2,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.bahan-baku.index'))
            ->delete(route('admin.bahan-baku.destroy', $activeBahan))
            ->assertRedirect(route('admin.bahan-baku.index'))
            ->assertSessionHasErrors('delete');

        // Bahan dengan relasi detail_draft_instruksi, detail_surat_jalan, dan notifikasi_alert ditolak saat dihapus
        $draftBahan = $this->makeIngredient('Sirup Hazelnut');
        $draft = DraftInstruksi::query()->create([
            'cabang_id' => $branch->cabang_id,
            'asal' => 'ic',
            'status' => 'pending',
            'dibuat_pada' => now(),
        ]);
        DetailDraftInstruksi::query()->create([
            'draft_id' => $draft->draft_id,
            'bahan_id' => $draftBahan->bahan_id,
            'qty_diminta' => 2,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.bahan-baku.index'))
            ->delete(route('admin.bahan-baku.destroy', $draftBahan))
            ->assertSessionHasErrors('delete');

        $sjBahan = $this->makeIngredient('Cup 16oz');
        $sj = SuratJalan::query()->create([
            'cabang_id' => $branch->cabang_id,
            'dc_id' => $dc->dc_id,
            'tanggal_kirim' => now(),
            'status' => 'disiapkan',
        ]);
        DetailSuratJalan::query()->create([
            'surat_jalan_id' => $sj->surat_jalan_id,
            'bahan_id' => $sjBahan->bahan_id,
            'qty_dikirim' => 10,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.bahan-baku.index'))
            ->delete(route('admin.bahan-baku.destroy', $sjBahan))
            ->assertSessionHasErrors('delete');

        $alertBahan = $this->makeIngredient('Matcha Powder');
        NotifikasiAlert::query()->create([
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $alertBahan->bahan_id,
            'tipe' => 'stok_menipis',
            'pesan' => 'Stok menipis',
            'status_baca' => false,
            'tanggal' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.bahan-baku.index'))
            ->delete(route('admin.bahan-baku.destroy', $alertBahan))
            ->assertSessionHasErrors('delete');
    }

    public function test_branch_and_dc_auto_initialize_and_prune_stock_and_reject_duplicate_names(): void
    {
        $admin = $this->makeAdmin();
        $ingredient = $this->makeIngredient('Biji Espresso');

        $this->actingAs($admin)
            ->post(route('admin.cabang.store'), [
                'nama_cabang' => 'Cabang Selatan',
                'alamat' => 'Jl. Selatan 10',
            ])
            ->assertRedirect(route('admin.cabang.index'));

        $branch = Cabang::query()->where('nama_cabang', 'Cabang Selatan')->firstOrFail();
        $this->assertDatabaseHas('stok_cabang', [
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $ingredient->bahan_id,
            'jumlah_stok' => 0,
            'stok_minimum' => 0,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.cabang.create'))
            ->post(route('admin.cabang.store'), [
                'nama_cabang' => 'cabang selatan',
                'alamat' => 'Jl. Lain',
            ])
            ->assertRedirect(route('admin.cabang.create'))
            ->assertSessionHasErrors('nama_cabang');

        $this->actingAs($admin)
            ->delete(route('admin.cabang.destroy', $branch))
            ->assertRedirect(route('admin.cabang.index'));
        $this->assertDatabaseCount('stok_cabang', 0);

        $this->actingAs($admin)
            ->post(route('admin.dc.store'), [
                'nama_dc' => 'DC Timur',
                'alamat' => 'Jl. Industri 5',
            ])
            ->assertRedirect(route('admin.dc.index'));

        $dc = Dc::query()->where('nama_dc', 'DC Timur')->firstOrFail();
        $this->assertDatabaseHas('stok_dc', [
            'dc_id' => $dc->dc_id,
            'bahan_id' => $ingredient->bahan_id,
            'jumlah_stok' => 0,
            'stok_minimum' => 0,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.dc.create'))
            ->post(route('admin.dc.store'), [
                'nama_dc' => 'dc timur',
                'alamat' => 'Jl. Industri 9',
            ])
            ->assertSessionHasErrors('nama_dc');

        $this->actingAs($admin)
            ->delete(route('admin.dc.destroy', $dc))
            ->assertRedirect(route('admin.dc.index'));
        $this->assertDatabaseCount('stok_dc', 0);
    }

    public function test_menu_produk_optionally_creates_default_regular_variant(): void
    {
        $admin = $this->makeAdmin();
        $category = KategoriMenu::query()->create(['nama_kategori' => 'Signature']);

        $this->actingAs($admin)
            ->post(route('admin.menu-produk.store'), [
                'kategori_menu_id' => $category->kategori_menu_id,
                'nama_menu' => 'KopSync Aren',
                'harga_jual' => 24000,
                'buat_varian_default' => '1',
            ])
            ->assertRedirect(route('admin.menu-produk.index'))
            ->assertSessionHas('success_action_label', 'Atur Resep Menu')
            ->assertSessionHas('success_action_url');

        $menuWithDefault = MenuProduk::query()->where('nama_menu', 'KopSync Aren')->firstOrFail();
        $this->assertDatabaseHas('varian_menu', [
            'menu_id' => $menuWithDefault->menu_id,
            'nama_varian' => 'Regular',
            'harga_tambahan' => 0,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.menu-produk.store'), [
                'kategori_menu_id' => $category->kategori_menu_id,
                'nama_menu' => 'Teh Melati',
                'harga_jual' => 15000,
                'buat_varian_default' => '0',
            ])
            ->assertRedirect(route('admin.menu-produk.index'));

        $menuWithoutDefault = MenuProduk::query()->where('nama_menu', 'Teh Melati')->firstOrFail();
        $this->assertDatabaseMissing('varian_menu', ['menu_id' => $menuWithoutDefault->menu_id]);
    }

    public function test_varian_menu_enforces_case_insensitive_composite_unique_and_shows_total_price(): void
    {
        $admin = $this->makeAdmin();
        $category = KategoriMenu::query()->create(['nama_kategori' => 'Kopi']);
        $menu = MenuProduk::query()->create([
            'kategori_menu_id' => $category->kategori_menu_id,
            'nama_menu' => 'Caramel Latte',
            'harga_jual' => 22000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.varian-menu.store'), [
                'menu_id' => $menu->menu_id,
                'nama_varian' => 'Iced',
                'harga_tambahan' => 3000,
            ])
            ->assertRedirect(route('admin.varian-menu.index'));

        $this->actingAs($admin)
            ->from(route('admin.varian-menu.create'))
            ->post(route('admin.varian-menu.store'), [
                'menu_id' => $menu->menu_id,
                'nama_varian' => 'iced',
                'harga_tambahan' => 5000,
            ])
            ->assertRedirect(route('admin.varian-menu.create'))
            ->assertSessionHasErrors('nama_varian');

        $this->actingAs($admin)
            ->get(route('admin.varian-menu.index'))
            ->assertOk()
            ->assertSee('Harga Jual Total')
            ->assertSee('Rp25.000');
    }

    public function test_recipe_quantity_must_be_greater_than_zero_and_shows_menu_variant_labels(): void
    {
        $category = KategoriMenu::query()->create(['nama_kategori' => 'Kopi']);
        $menu = MenuProduk::query()->create([
            'kategori_menu_id' => $category->kategori_menu_id,
            'nama_menu' => 'Latte',
            'harga_jual' => 25000,
        ]);
        $variant = VarianMenu::query()->create([
            'menu_id' => $menu->menu_id,
            'nama_varian' => 'Large',
            'harga_tambahan' => 5000,
        ]);
        $ingredient = $this->makeIngredient('Susu Segar');
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.resep-menu.create'))
            ->assertOk()
            ->assertSee('Latte - Large');

        $this->actingAs($admin)
            ->from(route('admin.resep-menu.create'))
            ->post(route('admin.resep-menu.store'), [
                'varian_id' => $variant->varian_id,
                'bahan_id' => $ingredient->bahan_id,
                'jumlah_bahan' => 0,
            ])
            ->assertSessionHasErrors('jumlah_bahan');

        $this->assertDatabaseCount('resep_menu', 0);

        $this->post(route('admin.resep-menu.store'), [
            'varian_id' => $variant->varian_id,
            'bahan_id' => $ingredient->bahan_id,
            'jumlah_bahan' => 0.25,
        ])->assertRedirect(route('admin.resep-menu.index'));

        // Duplikat (varian_id, bahan_id) ditolak
        $this->from(route('admin.resep-menu.create'))
            ->post(route('admin.resep-menu.store'), [
                'varian_id' => $variant->varian_id,
                'bahan_id' => $ingredient->bahan_id,
                'jumlah_bahan' => 0.5,
            ])
            ->assertSessionHasErrors('bahan_id');

        $resep = ResepMenu::query()->firstOrFail();

        $this->get(route('admin.resep-menu.edit', $resep))
            ->assertOk()
            ->assertSee('Latte - Large');

        $this->get(route('admin.resep-menu.index', ['q' => 'susu']))
            ->assertOk()
            ->assertSee('Latte - Large');
    }

    public function test_admin_can_assign_dc_role_and_unique_username_is_enforced(): void
    {
        $admin = $this->makeAdmin();
        $dc = Dc::query()->create([
            'nama_dc' => 'DC Utama',
            'alamat' => 'Jl. Gudang',
        ]);
        $payload = [
            'nama_lengkap' => 'Staf DC',
            'username' => 'staf.dc',
            'email' => 'staf.dc@example.test',
            'telepon' => '08123456789',
            'role_key' => 'dc',
            'dc_id' => $dc->dc_id,
            'status' => 'aktif',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->actingAs($admin)
            ->post(route('admin.pengguna.store'), $payload)
            ->assertRedirect(route('admin.pengguna.index'));

        $this->assertDatabaseHas('pengguna', [
            'username' => 'staf.dc',
            'dc_id' => $dc->dc_id,
            'cabang_id' => null,
        ]);

        $createdUser = Pengguna::query()->where('username', 'staf.dc')->firstOrFail();
        $this->get(route('admin.pengguna.edit', $createdUser))
            ->assertOk()
            ->assertSee('Reset Password');

        $this->from(route('admin.pengguna.edit', $createdUser))
            ->patch(route('admin.pengguna.password', $createdUser), [
                'password' => 'password456',
                'password_confirmation' => 'password456',
            ])
            ->assertRedirect(route('admin.pengguna.edit', $createdUser));
        $this->assertTrue(Hash::check('password456', $createdUser->fresh()->password_hash));

        $this->from(route('admin.pengguna.create'))
            ->post(route('admin.pengguna.store'), array_merge($payload, [
                'nama_lengkap' => 'Pengguna Lain',
                'email' => 'pengguna.lain@example.test',
            ]))
            ->assertRedirect(route('admin.pengguna.create'))
            ->assertSessionHasErrors('username');
    }

    public function test_last_active_admin_cannot_be_deactivated_from_admin_panel(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin.pengguna.index'))
            ->patch(route('admin.pengguna.status', $admin), ['status' => 'nonaktif'])
            ->assertRedirect(route('admin.pengguna.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame('aktif', $admin->fresh()->status);
    }

    public function test_admin_can_update_branch_and_dc_minimum_stock(): void
    {
        $admin = $this->makeAdmin();
        $branch = Cabang::query()->create([
            'nama_cabang' => 'Cabang Malang',
            'alamat' => 'Jl. Merdeka',
        ]);
        $dc = Dc::query()->create([
            'nama_dc' => 'DC Utama',
            'alamat' => 'Jl. Gudang',
        ]);
        $ingredient = $this->makeIngredient('Susu Segar');
        $branchStock = StokCabang::query()->create([
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $ingredient->bahan_id,
            'jumlah_stok' => 2,
            'stok_minimum' => 5,
        ]);
        $dcStock = StokDc::query()->create([
            'dc_id' => $dc->dc_id,
            'bahan_id' => $ingredient->bahan_id,
            'jumlah_stok' => 3,
            'stok_minimum' => 8,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.stok-cabang.index'))
            ->patch(route('admin.stok-cabang.minimum', $branchStock), ['stok_minimum' => 1])
            ->assertRedirect(route('admin.stok-cabang.index'));

        $this->assertDatabaseHas('stok_cabang', [
            'stok_cabang_id' => $branchStock->stok_cabang_id,
            'stok_minimum' => 1,
        ]);

        $this->from(route('admin.stok-dc.index'))
            ->patch(route('admin.stok-dc.minimum', $dcStock), ['stok_minimum' => 2])
            ->assertRedirect(route('admin.stok-dc.index'));

        $this->assertDatabaseHas('stok_dc', [
            'stok_dc_id' => $dcStock->stok_dc_id,
            'stok_minimum' => 2,
        ]);

        $this->from(route('admin.stok-cabang.index'))
            ->patch(route('admin.stok-cabang.minimum', $branchStock), ['stok_minimum' => -1])
            ->assertSessionHasErrors('stok_minimum');
    }

    public function test_non_admin_cannot_access_admin_panel_and_stock_filters_work_consistently(): void
    {
        $branch = Cabang::query()->create([
            'nama_cabang' => 'Cabang Malang',
            'alamat' => 'Jl. Merdeka',
        ]);
        $dc = Dc::query()->create([
            'nama_dc' => 'DC Pusat',
            'alamat' => 'Jl. Gudang',
        ]);
        $lowIngredient = $this->makeIngredient('Sirup Vanilla');
        $emptyIngredient = $this->makeIngredient('Bahan Kosong');
        $safeIngredient = $this->makeIngredient('Bahan Aman');

        StokCabang::query()->create([
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $lowIngredient->bahan_id,
            'jumlah_stok' => 2,
            'stok_minimum' => 10,
        ]);
        StokCabang::query()->create([
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $emptyIngredient->bahan_id,
            'jumlah_stok' => 0,
            'stok_minimum' => 0,
        ]);
        StokCabang::query()->create([
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $safeIngredient->bahan_id,
            'jumlah_stok' => 15,
            'stok_minimum' => 5,
        ]);
        StokDc::query()->create([
            'dc_id' => $dc->dc_id,
            'bahan_id' => $emptyIngredient->bahan_id,
            'jumlah_stok' => 0,
            'stok_minimum' => 0,
        ]);

        $cashier = Pengguna::query()->create([
            'nama_lengkap' => 'Kasir Test',
            'username' => 'kasir.test',
            'email' => 'kasir@example.test',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.kasir.db'),
            'cabang_id' => $branch->cabang_id,
            'status' => 'aktif',
        ]);

        $this->actingAs($cashier)->get(route('admin.dashboard'))->assertForbidden();

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.stok-cabang.index', ['status' => 'menipis', 'bahan_id' => $lowIngredient->bahan_id]))
            ->assertOk()
            ->assertSee('Sirup Vanilla')
            ->assertSee('Menipis');

        // 0/0 selalu berstatus Habis dan Perlu Perhatian, bukan Aman
        $amanResponse = $this->actingAs($admin)
            ->get(route('admin.stok-cabang.index', ['status' => 'aman']))
            ->assertOk()
            ->assertSee('Bahan Aman');
        $this->assertSame(['Bahan Aman'], $amanResponse->viewData('stocks')->pluck('bahan.nama_bahan')->all());

        $habisResponse = $this->actingAs($admin)
            ->get(route('admin.stok-cabang.index', ['status' => 'habis']))
            ->assertOk()
            ->assertSee('Bahan Kosong');
        $this->assertSame(['Bahan Kosong'], $habisResponse->viewData('stocks')->pluck('bahan.nama_bahan')->all());

        $perhatianResponse = $this->actingAs($admin)
            ->get(route('admin.stok-cabang.index', ['status' => 'perlu_perhatian']))
            ->assertOk()
            ->assertSee('Bahan Kosong')
            ->assertSee('Sirup Vanilla');
        $perhatianNames = $perhatianResponse->viewData('stocks')->pluck('bahan.nama_bahan')->sort()->values()->all();
        $this->assertSame(['Bahan Kosong', 'Sirup Vanilla'], $perhatianNames);

        // Parameter non-integer tidak menyebabkan error SQL
        $this->actingAs($admin)
            ->get(route('admin.stok-cabang.index', ['cabang_id' => 'abc', 'bahan_id' => 'xyz']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.stok-dc.index', ['dc_id' => 'abc', 'status' => 'habis']))
            ->assertOk()
            ->assertSee('Bahan Kosong');

        $this->actingAs($admin)
            ->get(route('admin.menu-produk.index', ['kategori_menu_id' => 'abc']))
            ->assertOk();
    }

    public function test_admin_role_overview_displays_database_user_counts(): void
    {
        $branch = Cabang::query()->create(['nama_cabang' => 'Cabang Pusat', 'alamat' => 'Jl. Pusat']);
        $admin = $this->makeAdmin();
        Pengguna::query()->create([
            'nama_lengkap' => 'Kasir Satu',
            'username' => 'kasir.satu',
            'email' => 'kasir.satu@example.test',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.kasir.db'),
            'cabang_id' => $branch->cabang_id,
            'status' => 'aktif',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.role'))
            ->assertOk()
            ->assertSee('Manajer Cabang')
            ->assertSee('Kelola Pengguna');
    }

    private function makeAdmin(): Pengguna
    {
        return Pengguna::query()->create([
            'nama_lengkap' => 'Admin Utama',
            'username' => 'admin.utama',
            'email' => 'admin@example.test',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.admin.db'),
            'status' => 'aktif',
        ]);
    }

    private function makeIngredient(string $name): BahanBaku
    {
        return BahanBaku::query()->create([
            'nama_bahan' => $name,
            'satuan' => 'liter',
            'harga_beli' => 18000,
            'nama_kemasan' => 'Botol',
            'isi_per_kemasan' => 1,
            'kategori' => 'Dairy',
            'minimum_order_qty' => 1,
        ]);
    }
}
