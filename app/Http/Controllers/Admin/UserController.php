<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\Pengguna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Halaman Kelola Pengguna + data awal (stores, roles, users).
     */
    public function index(): View
    {
        return view('admin.kelola.index', [
            'stores' => $this->stores(),
            'roles' => $this->roles(),
            'users' => $this->userList(),
        ]);
    }

    /**
     * Daftar pengguna terbaru (dipakai halaman untuk refresh setelah CRUD).
     */
    public function users(): JsonResponse
    {
        return response()->json(['users' => $this->userList()]);
    }

    /**
     * Buat pengguna baru. Payload view: { name, email, role, store, status, password }.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $user = Pengguna::query()->create([
            'nama_lengkap' => $data['name'],
            'username' => $this->uniqueUsername($data['email']),
            'email' => $data['email'],
            'password_hash' => $data['password'],
            'telepon' => null,
            'role' => config("menu.roles.{$data['role']}.db"),
            'cabang_id' => $data['cabang_id'],
            'dc_id' => null,
            'status' => $data['status'],
        ]);

        return response()->json([
            'message' => "Pengguna {$user->nama_lengkap} berhasil ditambahkan.",
            'user' => $this->mapUser($user),
        ], 201);
    }

    /**
     * Ubah pengguna. Password dikirim hanya bila ingin mengubah.
     */
    public function update(Request $request, Pengguna $user): JsonResponse
    {
        $data = $this->validatePayload($request, $user);

        $this->guardLastActiveAdmin($user, $data);

        $user->nama_lengkap = $data['name'];
        $user->email = $data['email'];
        $user->role = config("menu.roles.{$data['role']}.db");
        $user->cabang_id = $data['cabang_id'];
        $user->status = $data['status'];

        if (! empty($data['password'])) {
            $user->password_hash = $data['password'];
        }

        $user->save();

        return response()->json([
            'message' => 'Data ' . $user->nama_lengkap . ' berhasil diperbarui'
                . (! empty($data['password']) ? ' dan password diubah' : '') . '.',
            'user' => $this->mapUser($user),
        ]);
    }

    /**
     * Aktifkan / nonaktifkan pengguna.
     */
    public function updateStatus(Request $request, Pengguna $user): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        $roleKey = $this->roleKey($user->role);

        if ($data['status'] === 'nonaktif') {
            $this->guardLastActiveAdmin($user, ['role' => $roleKey, 'status' => 'nonaktif']);
        }

        if ($data['status'] === 'aktif' && $roleKey === 'manager') {
            $this->guardOneActiveManager($user->cabang_id, $user->user_id);
        }

        $user->status = $data['status'];
        $user->save();

        return response()->json([
            'message' => $data['status'] === 'aktif'
                ? $user->nama_lengkap . ' diaktifkan kembali.'
                : $user->nama_lengkap . ' dinonaktifkan.',
            'user' => $this->mapUser($user),
        ]);
    }

    /* =========================================================
       VALIDASI BISNIS
    ========================================================= */

    /**
     * @return array{name: string, email: string, role: string, cabang_id: ?int, status: string, password: ?string}
     */
    private function validatePayload(Request $request, ?Pengguna $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'lowercase', Rule::unique('pengguna', 'email')->ignore($existing?->user_id, 'user_id')],
            'role' => ['required', Rule::in(array_keys(config('menu.roles')))],
            'store' => ['nullable', 'integer'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'password' => [$existing === null ? 'required' : 'nullable', 'string', 'min:8'],
        ]);

        $errors = [];

        if ($data['role'] === 'dc') {
            $errors['role'] = 'Role DC Eksternal dibuat oleh sistem, bukan dari halaman ini.';
        }

        $needsStore = in_array($data['role'], ['kasir', 'ic', 'manager'], true);
        $store = $needsStore ? ($data['store'] ?? null) : null;

        if ($needsStore && $store === null) {
            $errors['store'] = 'Store wajib dipilih.';
        }

        if ($store !== null && ! Cabang::query()->where('cabang_id', $store)->exists()) {
            $errors['store'] = 'Store tidak ditemukan.';
        }

        if ($data['role'] === 'manager' && $data['status'] === 'aktif' && $store !== null) {
            $other = $this->activeManager($store, $existing?->user_id);

            if ($other !== null) {
                $errors['store'] = ($other->cabang->nama_cabang ?? 'Store tersebut')
                    . ' sudah punya Manager aktif (' . $other->nama_lengkap . ').';
            }
        }

        if ($existing !== null) {
            $errors = array_merge($errors, $this->lastActiveAdminErrors($existing, $data['role'], $data['status']));
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'role' => $data['role'],
            'cabang_id' => $store,
            'status' => $data['status'],
            'password' => $data['password'] ?? null,
        ];
    }

    /**
     * Satu-satunya Admin aktif tidak boleh diturunkan role-nya / dinonaktifkan.
     *
     * @param  array<string, string>  $data
     */
    private function guardLastActiveAdmin(Pengguna $user, array $data): void
    {
        $errors = $this->lastActiveAdminErrors($user, $data['role'], $data['status']);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, string>
     */
    private function lastActiveAdminErrors(Pengguna $user, string $roleKey, string $status): array
    {
        $wasActiveAdmin = $this->roleKey($user->role) === 'admin' && $user->status === 'aktif';
        $stillActiveAdmin = $roleKey === 'admin' && $status === 'aktif';

        if ($wasActiveAdmin && ! $stillActiveAdmin && $this->activeAdminCount() <= 1) {
            return ['name' => 'Ini satu-satunya Admin aktif, jadi role dan statusnya tidak bisa diubah.'];
        }

        return [];
    }

    /**
     * 1 cabang = maksimal 1 Manager aktif.
     */
    private function guardOneActiveManager(?int $cabangId, int $exceptUserId): void
    {
        $other = $this->activeManager($cabangId, $exceptUserId);

        if ($other !== null) {
            throw ValidationException::withMessages([
                'store' => ($other->cabang->nama_cabang ?? 'Store tersebut')
                    . ' sudah punya Manager aktif (' . $other->nama_lengkap . '). Nonaktifkan dulu Manager tersebut.',
            ]);
        }
    }

    private function activeManager(?int $cabangId, ?int $exceptUserId): ?Pengguna
    {
        return Pengguna::query()
            ->where('role', config('menu.roles.manager.db'))
            ->where('status', 'aktif')
            ->where('cabang_id', $cabangId)
            ->when($exceptUserId !== null, fn ($query) => $query->where('user_id', '!=', $exceptUserId))
            ->with('cabang')
            ->first();
    }

    private function activeAdminCount(): int
    {
        return Pengguna::query()
            ->where('role', config('menu.roles.admin.db'))
            ->where('status', 'aktif')
            ->count();
    }

    /* =========================================================
       PEMETAAN DATA
    ========================================================= */

    /**
     * @return array<string, string>
     */
    private function roles(): array
    {
        return collect(config('menu.roles'))
            ->mapWithKeys(fn ($meta, $key) => [$key => $meta['label']])
            ->all();
    }

    /**
     * @return array<string, array{name: string}>
     */
    private function stores(): array
    {
        return Cabang::query()
            ->orderBy('cabang_id')
            ->get()
            ->mapWithKeys(fn ($cabang) => [(string) $cabang->cabang_id => ['name' => $cabang->nama_cabang]])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function userList(): array
    {
        return Pengguna::query()
            ->orderBy('user_id')
            ->get()
            ->map(fn ($user) => $this->mapUser($user))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapUser(Pengguna $user): array
    {
        return [
            'id' => (int) $user->user_id,
            'name' => $user->nama_lengkap,
            'email' => $user->email,
            'role' => $this->roleKey($user->role),
            'store' => $user->cabang_id !== null ? (string) $user->cabang_id : null,
            'status' => $user->status,
            'last_login' => '-',
        ];
    }

    private function roleKey(?string $dbRole): ?string
    {
        foreach (config('menu.roles') as $key => $meta) {
            if ($meta['db'] === $dbRole) {
                return $key;
            }
        }

        return null;
    }

    private function uniqueUsername(string $email): string
    {
        $base = strtolower(strstr($email, '@', true) ?: 'user');
        $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?: 'user';

        $username = $base;
        $suffix = 1;

        while (Pengguna::query()->where('username', $username)->exists()) {
            $username = $base . '-' . $suffix++;
        }

        return $username;
    }
}
