<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Skema minimal pengguna untuk sqlite in-memory (tabel domain asli
        // ada di Supabase dan tidak dimigrasikan lewat Laravel migrations).
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
    }

    private function makeUser(array $overrides = []): Pengguna
    {
        return Pengguna::query()->create(array_merge([
            'nama_lengkap' => 'Andi Wijaya',
            'username' => 'andi.kasir1',
            'email' => 'andi.kasir1@kopsync.id',
            'password_hash' => Hash::make('password123'),
            'role' => config('menu.roles.kasir.db'),
            'cabang_id' => 1,
            'status' => 'aktif',
        ], $overrides));
    }

    public function test_login_page_is_visible_to_guests(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_user_can_login_with_username_and_is_redirected_to_role_home(): void
    {
        $this->makeUser();

        $response = $this->post(route('login.attempt'), [
            'identifier' => 'andi.kasir1',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('kasir.pos'));
        $this->assertAuthenticated();
    }

    public function test_user_can_login_with_email(): void
    {
        $this->makeUser();

        $this->post(route('login.attempt'), [
            'identifier' => 'andi.kasir1@kopsync.id',
            'password' => 'password123',
        ])->assertRedirect(route('kasir.pos'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->makeUser();

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'identifier' => 'andi.kasir1',
            'password' => 'salah',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->makeUser(['status' => 'nonaktif']);

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'identifier' => 'andi.kasir1',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_guest_cannot_access_protected_pages(): void
    {
        $this->get(route('kasir.pos'))->assertRedirect(route('login'));
    }

    public function test_role_middleware_blocks_wrong_role(): void
    {
        $this->makeUser();

        $this->actingAs(Pengguna::query()->first())
            ->get(route('admin.user'))
            ->assertForbidden();
    }

    public function test_logout_invalidates_session(): void
    {
        $this->makeUser();

        $this->actingAs(Pengguna::query()->first())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
