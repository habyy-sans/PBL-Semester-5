<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserManagementTest extends TestCase
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
    }

    private function makeAdmin(): Pengguna
    {
        return Pengguna::query()->create([
            'nama_lengkap' => 'Admin Utama',
            'username' => 'admin.utama',
            'email' => 'admin.utama@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.admin.db'),
            'cabang_id' => null,
            'status' => 'aktif',
        ]);
    }

    private function makeManager(int $cabangId, array $overrides = []): Pengguna
    {
        return Pengguna::query()->create(array_merge([
            'nama_lengkap' => 'Dewi Manager',
            'username' => 'dewi.manager' . $cabangId,
            'email' => 'dewi.manager' . $cabangId . '@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.manager.db'),
            'cabang_id' => $cabangId,
            'status' => 'aktif',
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rina Kasir',
            'email' => 'rina.kasir@kopsync.id',
            'role' => 'kasir',
            'store' => 1,
            'status' => 'aktif',
            'password' => 'password123',
        ], $overrides);
    }

    public function test_admin_page_is_visible_to_admin(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.user'))
            ->assertOk();
    }

    public function test_admin_can_create_user_with_mapped_role_and_hashed_password(): void
    {
        Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);

        $this->actingAs($this->makeAdmin())
            ->postJson(route('admin.user.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('user.role', 'kasir')
            ->assertJsonPath('user.store', '1');

        $user = Pengguna::query()->where('email', 'rina.kasir@kopsync.id')->firstOrFail();

        $this->assertSame(config('menu.roles.kasir.db'), $user->role);
        $this->assertSame(1, (int) $user->cabang_id);
        $this->assertSame('rina.kasir', $user->username);
        $this->assertTrue(Hash::check('password123', $user->password_hash));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        $this->makeAdmin();

        $payload = $this->payload(['email' => 'admin.utama@kopsync.id']);

        $this->actingAs(Pengguna::query()->where('role', config('menu.roles.admin.db'))->firstOrFail())
            ->postJson(route('admin.user.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_store_is_required_for_store_bound_roles(): void
    {
        Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);

        $this->actingAs($this->makeAdmin())
            ->postJson(route('admin.user.store'), $this->payload(['store' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('store');
    }

    public function test_second_active_manager_in_same_store_is_rejected(): void
    {
        Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        Cabang::query()->create(['nama_cabang' => 'Malang · Sawojajar']);
        $this->makeManager(1);

        $this->actingAs($this->makeAdmin())
            ->postJson(route('admin.user.store'), $this->payload([
                'name' => 'Manager Baru',
                'email' => 'manager.baru@kopsync.id',
                'role' => 'manager',
                'store' => 1,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('store');

        // Store lain yang belum punya manager tetap boleh
        $this->actingAs(Pengguna::query()->where('role', config('menu.roles.admin.db'))->firstOrFail())
            ->postJson(route('admin.user.store'), $this->payload([
                'name' => 'Manager Sawojajar',
                'email' => 'manager.sawojajar@kopsync.id',
                'role' => 'manager',
                'store' => 2,
            ]))
            ->assertCreated();
    }

    public function test_dc_role_cannot_be_created_from_page(): void
    {
        Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);

        $this->actingAs($this->makeAdmin())
            ->postJson(route('admin.user.store'), $this->payload([
                'role' => 'dc',
                'store' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_admin_can_update_user_and_optionally_password(): void
    {
        Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        $admin = $this->makeAdmin();

        $target = Pengguna::query()->create([
            'nama_lengkap' => 'Budi Kasir',
            'username' => 'budi.kasir',
            'email' => 'budi.kasir@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.kasir.db'),
            'cabang_id' => 1,
            'status' => 'aktif',
        ]);

        $this->actingAs($admin)
            ->putJson(route('admin.user.update', $target->user_id), $this->payload([
                'name' => 'Budi Santoso',
                'email' => 'budi.santoso@kopsync.id',
                'password' => null,
            ]))
            ->assertOk();

        $target->refresh();
        $this->assertSame('Budi Santoso', $target->nama_lengkap);
        $this->assertSame('budi.santoso@kopsync.id', $target->email);
        $this->assertTrue(Hash::check('password123', $target->password_hash), 'Password lama tidak boleh berubah');

        // Ubah password
        $this->actingAs($admin)
            ->putJson(route('admin.user.update', $target->user_id), $this->payload([
                'name' => 'Budi Santoso',
                'email' => 'budi.santoso@kopsync.id',
                'password' => 'barubanget123',
            ]))
            ->assertOk();

        $this->assertTrue($target->fresh()->password_hash !== null);
        $this->assertTrue(Hash::check('barubanget123', Pengguna::query()->find($target->user_id)->password_hash));
    }

    public function test_last_active_admin_cannot_be_deactivated_or_demoted(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->patchJson(route('admin.user.status', $admin->user_id), ['status' => 'nonaktif'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame('aktif', $admin->fresh()->status);

        // Turunkan role ke kasir juga ditolak
        $this->actingAs($admin)
            ->putJson(route('admin.user.update', $admin->user_id), $this->payload([
                'role' => 'kasir',
                'store' => null,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame(config('menu.roles.admin.db'), $admin->fresh()->role);
    }

    public function test_non_admin_cannot_be_managed_and_second_admin_frees_the_first(): void
    {
        $admin = $this->makeAdmin();

        $second = Pengguna::query()->create([
            'nama_lengkap' => 'Admin Kedua',
            'username' => 'admin.kedua',
            'email' => 'admin.kedua@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.admin.db'),
            'status' => 'aktif',
        ]);

        // Karena ada 2 admin aktif, admin pertama boleh dinonaktifkan
        $this->actingAs($admin)
            ->patchJson(route('admin.user.status', $admin->user_id), ['status' => 'nonaktif'])
            ->assertOk();

        $this->assertSame('nonaktif', $admin->fresh()->status);

        // Admin terakhir aktif tidak bisa dinonaktifkan
        $this->actingAs($second)
            ->patchJson(route('admin.user.status', $second->user_id), ['status' => 'nonaktif'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_manager_activation_is_blocked_when_store_has_active_manager(): void
    {
        Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        $this->makeManager(1);

        $inactive = Pengguna::query()->create([
            'nama_lengkap' => 'Manager Cadangan',
            'username' => 'manager.cadangan',
            'email' => 'manager.cadangan@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.manager.db'),
            'cabang_id' => 1,
            'status' => 'nonaktif',
        ]);

        $this->actingAs($this->makeAdmin())
            ->patchJson(route('admin.user.status', $inactive->user_id), ['status' => 'aktif'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('store');

        $this->assertSame('nonaktif', $inactive->fresh()->status);
    }

    public function test_user_list_endpoint_returns_mapped_users(): void
    {
        Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        $this->makeAdmin();

        $this->actingAs(Pengguna::query()->where('role', config('menu.roles.admin.db'))->firstOrFail())
            ->getJson(route('admin.user.data'))
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.role', 'admin')
            ->assertJsonPath('users.0.store', null)
            ->assertJsonPath('users.0.status', 'aktif');
    }
}
