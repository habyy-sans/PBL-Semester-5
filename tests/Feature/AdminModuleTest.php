<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\Cabang;
use App\Models\Dc;
use App\Models\KategoriMenu;
use App\Models\MenuProduk;
use App\Models\Pengguna;
use App\Models\StokCabang;
use App\Models\StokDc;
use App\Models\VarianMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cabang', function ($table): void {
            $table->id('cabang_id');
            $table->string('nama_cabang', 100);
            $table->text('alamat');
            $table->string('telepon', 20)->nullable();
        });
        Schema::create('dc', function ($table): void {
            $table->id('dc_id');
            $table->string('nama_dc', 100);
            $table->text('alamat');
            $table->string('telepon', 20)->nullable();
        });
        Schema::create('pengguna', function ($table): void {
            $table->id('user_id');
            $table->string('nama_lengkap', 150);
            $table->string('username', 50)->unique();
            $table->string('email', 150)->unique();
            $table->string('password_hash');
            $table->string('telepon', 20)->nullable();
            $table->string('role', 50);
            $table->foreignId('cabang_id')->nullable();
            $table->foreignId('dc_id')->nullable();
            $table->string('status', 20)->default('aktif');
            $table->string('remember_token', 100)->nullable();
        });
        Schema::create('bahan_baku', function ($table): void {
            $table->id('bahan_id');
            $table->string('nama_bahan', 150);
            $table->string('satuan', 30);
            $table->decimal('harga_beli', 12, 2);
            $table->string('nama_kemasan', 30);
            $table->decimal('isi_per_kemasan', 12, 3);
            $table->string('kategori', 50);
        });
        Schema::create('kategori_menu', function ($table): void {
            $table->id('kategori_menu_id');
            $table->string('nama_kategori', 100);
        });
        Schema::create('menu_produk', function ($table): void {
            $table->id('menu_id');
            $table->foreignId('kategori_menu_id');
            $table->string('nama_menu', 150);
            $table->decimal('harga_jual', 12, 2);
            $table->string('emoji', 16)->nullable();
        });
        Schema::create('varian_menu', function ($table): void {
            $table->id('varian_id');
            $table->foreignId('menu_id');
            $table->string('nama_varian', 100);
            $table->decimal('harga_tambahan', 12, 2);
        });
        Schema::create('resep_menu', function ($table): void {
            $table->id('resep_id');
            $table->foreignId('varian_id');
            $table->foreignId('bahan_id');
            $table->decimal('jumlah_bahan', 12, 3);
        });
        Schema::create('stok_cabang', function ($table): void {
            $table->id('stok_cabang_id');
            $table->foreignId('cabang_id');
            $table->foreignId('bahan_id');
            $table->decimal('jumlah_stok', 12, 3);
            $table->decimal('stok_minimum', 12, 3);
        });
        Schema::create('stok_dc', function ($table): void {
            $table->id('stok_dc_id');
            $table->foreignId('dc_id');
            $table->foreignId('bahan_id');
            $table->decimal('jumlah_stok', 12, 3);
            $table->decimal('stok_minimum', 12, 3);
        });
    }

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

    public function test_duplicate_menu_category_is_rejected(): void
    {
        KategoriMenu::query()->create(['nama_kategori' => 'Kopi']);

        $this->actingAs($this->makeAdmin())
            ->from(route('admin.kategori-menu.create'))
            ->post(route('admin.kategori-menu.store'), ['nama_kategori' => 'Kopi'])
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

    public function test_recipe_quantity_must_be_greater_than_zero(): void
    {
        $category = KategoriMenu::query()->create(['nama_kategori' => 'Kopi']);
        $menu = MenuProduk::query()->create([
            'kategori_menu_id' => $category->kategori_menu_id,
            'nama_menu' => 'Latte',
            'harga_jual' => 25000,
        ]);
        $variant = VarianMenu::query()->create([
            'menu_id' => $menu->menu_id,
            'nama_varian' => 'Regular',
            'harga_tambahan' => 0,
        ]);
        $ingredient = $this->makeIngredient('Susu Segar');

        $this->actingAs($this->makeAdmin())
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

        $this->get(route('admin.resep-menu.index', ['q' => 'susu']))
            ->assertOk()
            ->assertSee('Latte');
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

    public function test_non_admin_cannot_access_admin_panel_and_stock_filters_work(): void
    {
        $branch = Cabang::query()->create([
            'nama_cabang' => 'Cabang Malang',
            'alamat' => 'Jl. Merdeka',
        ]);
        $ingredient = $this->makeIngredient('Sirup Vanilla');
        StokCabang::query()->create([
            'cabang_id' => $branch->cabang_id,
            'bahan_id' => $ingredient->bahan_id,
            'jumlah_stok' => 2,
            'stok_minimum' => 10,
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

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.stok-cabang.index', ['status' => 'menipis', 'bahan_id' => $ingredient->bahan_id]))
            ->assertOk()
            ->assertSee('Sirup Vanilla')
            ->assertSee('Menipis');
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
        ]);
    }
}
