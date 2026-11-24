<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Dc;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $overrides = []): Pengguna
    {
        return Pengguna::query()->create(array_merge([
            'nama_lengkap' => 'Admin Utama',
            'username' => 'admin.utama',
            'email' => 'admin.utama@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.admin.db'),
            'cabang_id' => null,
            'dc_id' => null,
            'status' => 'aktif',
        ], $overrides));
    }

    private function makeManager(int $cabangId, array $overrides = []): Pengguna
    {
        return Pengguna::query()->create(array_merge([
            'nama_lengkap' => 'Dewi Manager',
            'username' => 'dewi.manager'.$cabangId,
            'email' => 'dewi.manager'.$cabangId.'@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.manager.db'),
            'cabang_id' => $cabangId,
            'dc_id' => null,
            'status' => 'aktif',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nama_lengkap' => 'Rina Kasir',
            'username' => 'rina.kasir',
            'email' => 'rina.kasir@kopsync.id',
            'telepon' => '08123456789',
            'role_key' => 'kasir',
            'cabang_id' => 1,
            'dc_id' => null,
            'status' => 'aktif',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_legacy_admin_user_route_redirects_and_pengguna_index_is_visible_to_admin(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.user'))
            ->assertRedirect(route('admin.pengguna.index'));

        $this->actingAs($admin)
            ->get(route('admin.pengguna.index'))
            ->assertOk()
            ->assertSee('Admin Utama');
    }

    public function test_admin_can_create_user_with_mapped_role_and_hashed_password(): void
    {
        $branch = Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.pengguna.store'), $this->payload(['cabang_id' => $branch->cabang_id]))
            ->assertRedirect(route('admin.pengguna.index'));

        $user = Pengguna::query()->where('email', 'rina.kasir@kopsync.id')->firstOrFail();

        $this->assertSame(config('menu.roles.kasir.db'), $user->role);
        $this->assertSame($branch->cabang_id, (int) $user->cabang_id);
        $this->assertNull($user->dc_id);
        $this->assertSame('rina.kasir', $user->username);
        $this->assertTrue(Hash::check('password123', $user->password_hash));
    }

    public function test_case_insensitive_duplicate_email_and_username_are_rejected(): void
    {
        $branch = Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin.pengguna.create'))
            ->post(route('admin.pengguna.store'), $this->payload([
                'cabang_id' => $branch->cabang_id,
                'email' => 'ADMIN.UTAMA@KOPSYNC.ID',
                'username' => 'rina.unik',
            ]))
            ->assertRedirect(route('admin.pengguna.create'))
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->from(route('admin.pengguna.create'))
            ->post(route('admin.pengguna.store'), $this->payload([
                'cabang_id' => $branch->cabang_id,
                'username' => 'Admin.Utama',
                'email' => 'rina.unik@kopsync.id',
            ]))
            ->assertRedirect(route('admin.pengguna.create'))
            ->assertSessionHasErrors('username');
    }

    public function test_invalid_username_format_is_rejected(): void
    {
        $branch = Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        $admin = $this->makeAdmin();

        foreach (['rina kasir', 'rina@kasir'] as $badUsername) {
            $this->actingAs($admin)
                ->from(route('admin.pengguna.create'))
                ->post(route('admin.pengguna.store'), $this->payload([
                    'cabang_id' => $branch->cabang_id,
                    'username' => $badUsername,
                ]))
                ->assertRedirect(route('admin.pengguna.create'))
                ->assertSessionHasErrors('username');
        }
    }

    public function test_location_is_required_for_branch_and_dc_roles(): void
    {
        $admin = $this->makeAdmin();
        $dc = Dc::query()->create(['nama_dc' => 'DC Malang', 'alamat' => 'Jl. Gudang']);

        $this->actingAs($admin)
            ->from(route('admin.pengguna.create'))
            ->post(route('admin.pengguna.store'), $this->payload([
                'role_key' => 'kasir',
                'cabang_id' => null,
            ]))
            ->assertRedirect(route('admin.pengguna.create'))
            ->assertSessionHasErrors('cabang_id');

        $this->actingAs($admin)
            ->from(route('admin.pengguna.create'))
            ->post(route('admin.pengguna.store'), $this->payload([
                'nama_lengkap' => 'Staf DC Baru',
                'username' => 'staf.dc.baru',
                'email' => 'staf.dc.baru@kopsync.id',
                'role_key' => 'dc',
                'cabang_id' => null,
                'dc_id' => null,
            ]))
            ->assertRedirect(route('admin.pengguna.create'))
            ->assertSessionHasErrors('dc_id');

        $this->actingAs($admin)
            ->post(route('admin.pengguna.store'), $this->payload([
                'nama_lengkap' => 'Staf DC Baru',
                'username' => 'staf.dc.baru',
                'email' => 'staf.dc.baru@kopsync.id',
                'role_key' => 'dc',
                'cabang_id' => null,
                'dc_id' => $dc->dc_id,
            ]))
            ->assertRedirect(route('admin.pengguna.index'));

        $this->assertDatabaseHas('pengguna', [
            'username' => 'staf.dc.baru',
            'dc_id' => $dc->dc_id,
            'cabang_id' => null,
        ]);
    }

    public function test_second_active_manager_in_same_branch_is_rejected(): void
    {
        $branch1 = Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        $branch2 = Cabang::query()->create(['nama_cabang' => 'Malang · Sawojajar']);
        $this->makeManager($branch1->cabang_id);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin.pengguna.create'))
            ->post(route('admin.pengguna.store'), $this->payload([
                'nama_lengkap' => 'Manager Baru',
                'username' => 'manager.baru',
                'email' => 'manager.baru@kopsync.id',
                'role_key' => 'manager',
                'cabang_id' => $branch1->cabang_id,
            ]))
            ->assertRedirect(route('admin.pengguna.create'))
            ->assertSessionHasErrors('cabang_id');

        $this->actingAs($admin)
            ->post(route('admin.pengguna.store'), $this->payload([
                'nama_lengkap' => 'Manager Sawojajar',
                'username' => 'manager.sawojajar',
                'email' => 'manager.sawojajar@kopsync.id',
                'role_key' => 'manager',
                'cabang_id' => $branch2->cabang_id,
            ]))
            ->assertRedirect(route('admin.pengguna.index'));
    }

    public function test_admin_can_update_user_and_optionally_keep_or_reset_password(): void
    {
        $branch = Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        $admin = $this->makeAdmin();

        $target = Pengguna::query()->create([
            'nama_lengkap' => 'Budi Kasir',
            'username' => 'budi.kasir',
            'email' => 'budi.kasir@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.kasir.db'),
            'cabang_id' => $branch->cabang_id,
            'status' => 'aktif',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.pengguna.update', $target), $this->payload([
                'nama_lengkap' => 'Budi Santoso',
                'username' => 'budi.santoso',
                'email' => 'budi.santoso@kopsync.id',
                'cabang_id' => $branch->cabang_id,
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertRedirect(route('admin.pengguna.index'));

        $target->refresh();
        $this->assertSame('Budi Santoso', $target->nama_lengkap);
        $this->assertSame('budi.santoso@kopsync.id', $target->email);
        $this->assertTrue(Hash::check('password123', $target->password_hash), 'Password lama tidak boleh berubah saat dikosongkan');

        $this->actingAs($admin)
            ->from(route('admin.pengguna.edit', $target))
            ->patch(route('admin.pengguna.password', $target), [
                'password' => 'barubanget123',
                'password_confirmation' => 'barubanget123',
            ])
            ->assertRedirect(route('admin.pengguna.edit', $target))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('barubanget123', $target->fresh()->password_hash));

        $this->actingAs($admin)
            ->get(route('admin.pengguna.edit', $target))
            ->assertOk()
            ->assertSee('Password pengguna berhasil direset.');
    }

    public function test_last_active_admin_cannot_be_deactivated_demoted_or_deleted(): void
    {
        $branch = Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('admin.pengguna.index'))
            ->patch(route('admin.pengguna.status', $admin), ['status' => 'nonaktif'])
            ->assertRedirect(route('admin.pengguna.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame('aktif', $admin->fresh()->status);

        $this->actingAs($admin)
            ->from(route('admin.pengguna.edit', $admin))
            ->put(route('admin.pengguna.update', $admin), $this->payload([
                'nama_lengkap' => $admin->nama_lengkap,
                'username' => $admin->username,
                'email' => $admin->email,
                'role_key' => 'kasir',
                'cabang_id' => $branch->cabang_id,
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertRedirect(route('admin.pengguna.edit', $admin))
            ->assertSessionHasErrors('status');

        $this->assertSame(config('menu.roles.admin.db'), $admin->fresh()->role);

        $this->actingAs($admin)
            ->from(route('admin.pengguna.index'))
            ->delete(route('admin.pengguna.destroy', $admin))
            ->assertRedirect(route('admin.pengguna.index'))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('pengguna', ['user_id' => $admin->user_id]);
    }

    public function test_second_admin_allows_deactivating_first_and_blocks_remaining_admin_deactivation(): void
    {
        $admin = $this->makeAdmin();
        $second = $this->makeAdmin([
            'nama_lengkap' => 'Admin Kedua',
            'username' => 'admin.kedua',
            'email' => 'admin.kedua@kopsync.id',
        ]);

        $this->actingAs($second)
            ->from(route('admin.pengguna.index'))
            ->patch(route('admin.pengguna.status', $admin), ['status' => 'nonaktif'])
            ->assertRedirect(route('admin.pengguna.index'));

        $this->assertSame('nonaktif', $admin->fresh()->status);

        $this->actingAs($second)
            ->from(route('admin.pengguna.index'))
            ->patch(route('admin.pengguna.status', $second), ['status' => 'nonaktif'])
            ->assertRedirect(route('admin.pengguna.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame('aktif', $second->fresh()->status);
    }

    public function test_manager_activation_is_blocked_when_branch_has_active_manager(): void
    {
        $branch = Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        $this->makeManager($branch->cabang_id);

        $inactive = Pengguna::query()->create([
            'nama_lengkap' => 'Manager Cadangan',
            'username' => 'manager.cadangan',
            'email' => 'manager.cadangan@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.manager.db'),
            'cabang_id' => $branch->cabang_id,
            'status' => 'nonaktif',
        ]);

        $this->actingAs($this->makeAdmin())
            ->from(route('admin.pengguna.index'))
            ->patch(route('admin.pengguna.status', $inactive), ['status' => 'aktif'])
            ->assertRedirect(route('admin.pengguna.index'))
            ->assertSessionHasErrors('cabang_id');

        $this->assertSame('nonaktif', $inactive->fresh()->status);
    }

    public function test_activating_branch_or_dc_user_without_placement_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        $unassignedCashier = Pengguna::query()->create([
            'nama_lengkap' => 'Kasir Tanpa Cabang',
            'username' => 'kasir.tanpa.cabang',
            'email' => 'kasir.tanpa.cabang@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.kasir.db'),
            'cabang_id' => null,
            'dc_id' => null,
            'status' => 'nonaktif',
        ]);

        $unassignedDc = Pengguna::query()->create([
            'nama_lengkap' => 'DC Tanpa Gudang',
            'username' => 'dc.tanpa.gudang',
            'email' => 'dc.tanpa.gudang@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.dc.db'),
            'cabang_id' => null,
            'dc_id' => null,
            'status' => 'nonaktif',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.pengguna.index'))
            ->patch(route('admin.pengguna.status', $unassignedCashier), ['status' => 'aktif'])
            ->assertRedirect(route('admin.pengguna.index'))
            ->assertSessionHasErrors('status');

        $this->actingAs($admin)
            ->from(route('admin.pengguna.index'))
            ->patch(route('admin.pengguna.status', $unassignedDc), ['status' => 'aktif'])
            ->assertRedirect(route('admin.pengguna.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame('nonaktif', $unassignedCashier->fresh()->status);
        $this->assertSame('nonaktif', $unassignedDc->fresh()->status);
    }

    public function test_pengguna_index_filters_and_invalid_numeric_queries_handle_cleanly(): void
    {
        $branch = Cabang::query()->create(['nama_cabang' => 'Malang · Alun-Alun']);
        $admin = $this->makeAdmin();
        $this->makeManager($branch->cabang_id);

        $filteredResponse = $this->actingAs($admin)
            ->get(route('admin.pengguna.index', ['q' => 'dewi', 'role' => 'manager', 'status' => 'aktif']))
            ->assertOk()
            ->assertSee('Dewi Manager')
            ->assertDontSee('admin.utama@kopsync.id');
        $this->assertSame(['dewi.manager'.$branch->cabang_id], $filteredResponse->viewData('users')->pluck('username')->all());

        $this->actingAs($admin)
            ->get(route('admin.pengguna.index', ['cabang_id' => 'bukan-angka', 'dc_id' => 'xyz']))
            ->assertOk()
            ->assertSee('Admin Utama');
    }
}
