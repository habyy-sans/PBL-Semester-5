<?php

namespace Tests\Feature;

use App\Models\BahanBaku;
use App\Models\KategoriMenu;
use App\Models\MenuProduk;
use App\Models\Pengguna;
use App\Models\ResepMenu;
use App\Models\VarianMenu;
use App\Services\CatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRecipeBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_multi_ingredient_recipe_and_sync_to_pos_catalog(): void
    {
        $admin = $this->makeAdmin();
        [, $varian] = $this->makeLatteMenu();
        $beans = $this->makeIngredient('Arabica Beans', 'g', 120000, 1000);
        $syrup = $this->makeIngredient('Gula Cair', 'ml', 20000, 1000);
        $milk = $this->makeIngredient('Fresh Milk', 'ml', 24000, 1000);

        $this->actingAs($admin)
            ->post(route('admin.resep-menu.store'), [
                'varian_id' => $varian->varian_id,
                'items' => [
                    ['bahan_id' => $beans->bahan_id, 'jumlah_bahan' => 10],
                    ['bahan_id' => $syrup->bahan_id, 'jumlah_bahan' => 40],
                    ['bahan_id' => $milk->bahan_id, 'jumlah_bahan' => 70],
                ],
            ])
            ->assertRedirect(route('admin.resep-menu.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('resep_menu', 3);
        $this->assertDatabaseHas('resep_menu', ['varian_id' => $varian->varian_id, 'bahan_id' => $beans->bahan_id, 'jumlah_bahan' => 10]);
        $this->assertDatabaseHas('resep_menu', ['varian_id' => $varian->varian_id, 'bahan_id' => $syrup->bahan_id, 'jumlah_bahan' => 40]);
        $this->assertDatabaseHas('resep_menu', ['varian_id' => $varian->varian_id, 'bahan_id' => $milk->bahan_id, 'jumlah_bahan' => 70]);

        $posMenus = app(CatalogService::class)->menus();
        $latteRecipe = (array) $posMenus[$varian->menu_id]['recipe'];
        $this->assertSame(10.0, $latteRecipe[$beans->bahan_id]);
        $this->assertSame(40.0, $latteRecipe[$syrup->bahan_id]);
        $this->assertSame(70.0, $latteRecipe[$milk->bahan_id]);
    }

    public function test_admin_can_update_add_and_remove_ingredients_atomically_via_builder(): void
    {
        $admin = $this->makeAdmin();
        [, $varian] = $this->makeLatteMenu();
        $beans = $this->makeIngredient('Arabica Beans', 'g', 120000, 1000);
        $syrup = $this->makeIngredient('Gula Cair', 'ml', 20000, 1000);
        $milk = $this->makeIngredient('Fresh Milk', 'ml', 24000, 1000);
        $cocoa = $this->makeIngredient('Cokelat Bubuk', 'g', 50000, 500);

        ResepMenu::query()->create(['varian_id' => $varian->varian_id, 'bahan_id' => $beans->bahan_id, 'jumlah_bahan' => 10]);
        ResepMenu::query()->create(['varian_id' => $varian->varian_id, 'bahan_id' => $syrup->bahan_id, 'jumlah_bahan' => 40]);
        ResepMenu::query()->create(['varian_id' => $varian->varian_id, 'bahan_id' => $milk->bahan_id, 'jumlah_bahan' => 70]);

        $this->actingAs($admin)
            ->get(route('admin.resep-menu.builder', $varian))
            ->assertOk()
            ->assertSee('Caffe Latte - Regular')
            ->assertSee('Pratinjau Takaran SOP');

        // Ubah takaran beans (10 -> 15), hapus gula cair, pertahankan susu (70), tambah cokelat (12)
        $this->actingAs($admin)
            ->put(route('admin.resep-menu.sync', $varian), [
                'items' => [
                    ['bahan_id' => $beans->bahan_id, 'jumlah_bahan' => 15],
                    ['bahan_id' => $milk->bahan_id, 'jumlah_bahan' => 70],
                    ['bahan_id' => $cocoa->bahan_id, 'jumlah_bahan' => 12],
                ],
            ])
            ->assertRedirect(route('admin.resep-menu.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('resep_menu', 3);
        $this->assertDatabaseHas('resep_menu', ['varian_id' => $varian->varian_id, 'bahan_id' => $beans->bahan_id, 'jumlah_bahan' => 15]);
        $this->assertDatabaseMissing('resep_menu', ['varian_id' => $varian->varian_id, 'bahan_id' => $syrup->bahan_id]);
        $this->assertDatabaseHas('resep_menu', ['varian_id' => $varian->varian_id, 'bahan_id' => $cocoa->bahan_id, 'jumlah_bahan' => 12]);
    }

    public function test_recipe_builder_rejects_duplicate_ingredients_empty_items_and_non_positive_qty(): void
    {
        $admin = $this->makeAdmin();
        [, $varian] = $this->makeLatteMenu();
        $beans = $this->makeIngredient('Arabica Beans', 'g', 120000, 1000);

        $this->actingAs($admin)
            ->from(route('admin.resep-menu.builder', $varian))
            ->put(route('admin.resep-menu.sync', $varian), [
                'items' => [
                    ['bahan_id' => $beans->bahan_id, 'jumlah_bahan' => 10],
                    ['bahan_id' => $beans->bahan_id, 'jumlah_bahan' => 5],
                ],
            ])
            ->assertRedirect(route('admin.resep-menu.builder', $varian))
            ->assertSessionHasErrors('items.1.bahan_id');

        $this->actingAs($admin)
            ->from(route('admin.resep-menu.builder', $varian))
            ->put(route('admin.resep-menu.sync', $varian), [
                'items' => [
                    ['bahan_id' => $beans->bahan_id, 'jumlah_bahan' => 0],
                ],
            ])
            ->assertSessionHasErrors('items.0.jumlah_bahan');

        $this->actingAs($admin)
            ->from(route('admin.resep-menu.builder', $varian))
            ->put(route('admin.resep-menu.sync', $varian), [
                'items' => [],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('resep_menu', 0);
    }

    public function test_admin_can_clear_variant_recipe_without_affecting_other_variants(): void
    {
        $admin = $this->makeAdmin();
        [$menu, $v1] = $this->makeLatteMenu();
        $v2 = VarianMenu::query()->create([
            'menu_id' => $menu->menu_id,
            'nama_varian' => 'Large',
            'harga_tambahan' => 5000,
        ]);
        $beans = $this->makeIngredient('Arabica Beans', 'g', 120000, 1000);

        ResepMenu::query()->create(['varian_id' => $v1->varian_id, 'bahan_id' => $beans->bahan_id, 'jumlah_bahan' => 10]);
        ResepMenu::query()->create(['varian_id' => $v2->varian_id, 'bahan_id' => $beans->bahan_id, 'jumlah_bahan' => 15]);

        $this->actingAs($admin)
            ->delete(route('admin.resep-menu.clear', $v1))
            ->assertRedirect(route('admin.resep-menu.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('resep_menu', ['varian_id' => $v1->varian_id]);
        $this->assertDatabaseHas('resep_menu', ['varian_id' => $v2->varian_id, 'jumlah_bahan' => 15]);
    }

    public function test_resep_index_groups_by_variant_shows_hpp_and_filters_accurately(): void
    {
        $admin = $this->makeAdmin();
        [$category, $vLatte] = $this->makeLatteMenuWithCategory();
        $americano = MenuProduk::query()->create([
            'kategori_menu_id' => $category->kategori_menu_id,
            'nama_menu' => 'Americano',
            'harga_jual' => 18000,
        ]);
        $vAmericano = VarianMenu::query()->create([
            'menu_id' => $americano->menu_id,
            'nama_varian' => 'Regular',
            'harga_tambahan' => 0,
        ]);

        $beans = $this->makeIngredient('Arabica Beans', 'g', 120000, 1000);
        $syrup = $this->makeIngredient('Gula Cair', 'ml', 20000, 1000);
        $milk = $this->makeIngredient('Fresh Milk', 'ml', 24000, 1000);

        ResepMenu::query()->create(['varian_id' => $vLatte->varian_id, 'bahan_id' => $beans->bahan_id, 'jumlah_bahan' => 10]);
        ResepMenu::query()->create(['varian_id' => $vLatte->varian_id, 'bahan_id' => $syrup->bahan_id, 'jumlah_bahan' => 40]);
        ResepMenu::query()->create(['varian_id' => $vLatte->varian_id, 'bahan_id' => $milk->bahan_id, 'jumlah_bahan' => 70]);

        $indexResp = $this->actingAs($admin)
            ->get(route('admin.resep-menu.index'))
            ->assertOk()
            ->assertSee('Caffe Latte - Regular')
            ->assertSee('Arabica Beans')
            ->assertSee('Gula Cair')
            ->assertSee('Fresh Milk')
            ->assertSee('Rp3.680')
            ->assertSee('Americano - Regular');

        $this->assertSame(['all' => 2, 'lengkap' => 1, 'belum' => 1], $indexResp->viewData('counts'));

        $lengkap = $this->actingAs($admin)->get(route('admin.resep-menu.index', ['status' => 'lengkap']))->assertOk();
        $this->assertSame([$vLatte->varian_id], $lengkap->viewData('variants')->pluck('varian_id')->all());

        $belum = $this->actingAs($admin)->get(route('admin.resep-menu.index', ['status' => 'belum']))->assertOk();
        $this->assertSame([$vAmericano->varian_id], $belum->viewData('variants')->pluck('varian_id')->all());

        $byIngredientSearch = $this->actingAs($admin)->get(route('admin.resep-menu.index', ['q' => 'arabica']))->assertOk();
        $this->assertSame([$vLatte->varian_id], $byIngredientSearch->viewData('variants')->pluck('varian_id')->all());

        $byIngredientFilter = $this->actingAs($admin)->get(route('admin.resep-menu.index', ['bahan_id' => $milk->bahan_id]))->assertOk();
        $this->assertSame([$vLatte->varian_id], $byIngredientFilter->viewData('variants')->pluck('varian_id')->all());

        $this->actingAs($admin)
            ->get(route('admin.resep-menu.index', ['kategori_menu_id' => 'abc', 'menu_id' => 'xyz', 'bahan_id' => 'bad']))
            ->assertOk();
    }

    public function test_creating_menu_produk_with_default_variant_can_save_multi_ingredient_recipe_atomically(): void
    {
        $admin = $this->makeAdmin();
        $category = KategoriMenu::query()->create(['nama_kategori' => 'Non Kopi']);
        $matcha = $this->makeIngredient('Matcha Powder', 'g', 80000, 500);
        $milk = $this->makeIngredient('Fresh Milk', 'ml', 24000, 1000);

        $this->actingAs($admin)
            ->get(route('admin.menu-produk.create'))
            ->assertOk()
            ->assertSee('Resep Awal Varian');

        $this->actingAs($admin)
            ->post(route('admin.menu-produk.store'), [
                'kategori_menu_id' => $category->kategori_menu_id,
                'nama_menu' => 'Matcha Latte',
                'harga_jual' => 28000,
                'buat_varian_default' => '1',
                'recipe_items' => [
                    ['bahan_id' => $matcha->bahan_id, 'jumlah_bahan' => 12],
                    ['bahan_id' => $milk->bahan_id, 'jumlah_bahan' => 150],
                    ['bahan_id' => '', 'jumlah_bahan' => ''],
                ],
            ])
            ->assertRedirect(route('admin.menu-produk.index'))
            ->assertSessionHas('success_action_label', 'Ubah Resep');

        $menu = MenuProduk::query()->where('nama_menu', 'Matcha Latte')->firstOrFail();
        $varian = VarianMenu::query()->where('menu_id', $menu->menu_id)->firstOrFail();
        $this->assertSame('Regular', $varian->nama_varian);
        $this->assertDatabaseCount('resep_menu', 2);

        // Duplikat bahan saat buat menu harus menggagalkan seluruh transaksi pembuatan menu
        $this->actingAs($admin)
            ->from(route('admin.menu-produk.create'))
            ->post(route('admin.menu-produk.store'), [
                'kategori_menu_id' => $category->kategori_menu_id,
                'nama_menu' => 'Menu Gagal Rollback',
                'harga_jual' => 20000,
                'buat_varian_default' => '1',
                'recipe_items' => [
                    ['bahan_id' => $matcha->bahan_id, 'jumlah_bahan' => 10],
                    ['bahan_id' => $matcha->bahan_id, 'jumlah_bahan' => 5],
                ],
            ])
            ->assertRedirect(route('admin.menu-produk.create'))
            ->assertSessionHasErrors('recipe_items.1.bahan_id');

        $this->assertDatabaseMissing('menu_produk', ['nama_menu' => 'Menu Gagal Rollback']);
    }

    private function makeAdmin(): Pengguna
    {
        return Pengguna::query()->create([
            'nama_lengkap' => 'Admin Utama',
            'username' => 'admin.recipe',
            'email' => 'admin@recipe.test',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.admin.db'),
            'status' => 'aktif',
        ]);
    }

    /**
     * @return array{0: MenuProduk, 1: VarianMenu}
     */
    private function makeLatteMenu(): array
    {
        [, $varian, $menu] = $this->makeLatteMenuWithCategory();

        return [$menu, $varian];
    }

    /**
     * @return array{0: KategoriMenu, 1: VarianMenu, 2: MenuProduk}
     */
    private function makeLatteMenuWithCategory(): array
    {
        $category = KategoriMenu::query()->create(['nama_kategori' => 'Kopi']);
        $menu = MenuProduk::query()->create([
            'kategori_menu_id' => $category->kategori_menu_id,
            'nama_menu' => 'Caffe Latte',
            'harga_jual' => 25000,
        ]);
        $varian = VarianMenu::query()->create([
            'menu_id' => $menu->menu_id,
            'nama_varian' => 'Regular',
            'harga_tambahan' => 0,
        ]);

        return [$category, $varian, $menu];
    }

    private function makeIngredient(string $name, string $unit, float $price, float $packSize): BahanBaku
    {
        return BahanBaku::query()->create([
            'nama_bahan' => $name,
            'kategori' => 'Bahan Baku',
            'satuan' => $unit,
            'harga_beli' => $price,
            'nama_kemasan' => 'Pack',
            'isi_per_kemasan' => $packSize,
            'minimum_order_qty' => 1,
        ]);
    }
}
