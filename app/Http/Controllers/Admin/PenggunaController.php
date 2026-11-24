<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\Dc;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PenggunaController extends Controller
{
    public function index(Request $request): View
    {
        $query = Pengguna::query()->with(['cabang', 'dc']);
        $queryKeyword = $request->query('q');
        $keyword = is_string($queryKeyword) ? trim($queryKeyword) : '';

        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $pattern = '%'.mb_strtolower($keyword).'%';
                $builder
                    ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(username) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
            });
        }

        $activeFilters = [];

        $roleParam = $request->query('role');
        if (is_string($roleParam) && $roleParam !== '' && isset(config('menu.roles')[$roleParam])) {
            $query->where('role', config("menu.roles.{$roleParam}.db"));
            $activeFilters['role'] = $roleParam;
        }

        foreach (['cabang_id', 'dc_id'] as $idFilter) {
            $val = $request->query($idFilter);
            if (is_string($val) && ctype_digit($val)) {
                $query->where($idFilter, (int) $val);
                $activeFilters[$idFilter] = $val;
            }
        }

        $statusParam = $request->query('status');
        if (is_string($statusParam) && in_array($statusParam, ['aktif', 'nonaktif'], true)) {
            $query->where('status', $statusParam);
            $activeFilters['status'] = $statusParam;
        }

        $branchesWithoutManager = Cabang::query()
            ->whereDoesntHave('pengguna', function (Builder $builder): void {
                $builder->where('role', config('menu.roles.manager.db'))
                    ->where('status', 'aktif');
            })
            ->orderBy('nama_cabang')
            ->get(['cabang_id', 'nama_cabang']);

        return view('admin.pengguna.index', [
            'users' => $query->orderBy('nama_lengkap')->orderBy('user_id')->paginate(12)->withQueryString(),
            'branches' => Cabang::query()->orderBy('nama_cabang')->get(),
            'distributionCenters' => Dc::query()->orderBy('nama_dc')->get(),
            'roles' => config('menu.roles'),
            'keyword' => $keyword,
            'filters' => $activeFilters,
            'hasActiveFilters' => $keyword !== '' || $activeFilters !== [],
            'branchesWithoutManager' => $branchesWithoutManager,
            'totals' => [
                'all' => Pengguna::query()->count(),
                'active' => Pengguna::query()->where('status', 'aktif')->count(),
                'inactive' => Pengguna::query()->where('status', 'nonaktif')->count(),
                'no_manager' => $branchesWithoutManager->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $roleKey = $request->query('role_key');
        $cabangId = $request->query('cabang_id');
        $dcId = $request->query('dc_id');

        $prefill = [
            'role_key' => is_string($roleKey) && isset(config('menu.roles')[$roleKey]) ? $roleKey : '',
            'cabang_id' => is_string($cabangId) && ctype_digit($cabangId) ? $cabangId : '',
            'dc_id' => is_string($dcId) && ctype_digit($dcId) ? $dcId : '',
        ];

        return $this->formView(null, $prefill);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedUser($request);

        DB::transaction(function () use ($data): void {
            $this->assertBusinessRules($data);

            Pengguna::query()->create([
                'nama_lengkap' => $data['nama_lengkap'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $data['password'],
                'telepon' => $data['telepon'] ?? null,
                'role' => config("menu.roles.{$data['role_key']}.db"),
                'cabang_id' => $data['cabang_id'] ?? null,
                'dc_id' => $data['dc_id'] ?? null,
                'status' => $data['status'],
            ]);
        });

        return redirect()->route('admin.pengguna.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(Pengguna $pengguna): View
    {
        return $this->formView($pengguna);
    }

    public function update(Request $request, Pengguna $pengguna): RedirectResponse
    {
        $data = $this->validatedUser($request, $pengguna);

        DB::transaction(function () use ($data, $pengguna): void {
            $lockedUser = Pengguna::query()->whereKey($pengguna->user_id)->lockForUpdate()->firstOrFail();
            $this->assertBusinessRules($data, $lockedUser);

            $lockedUser->fill([
                'nama_lengkap' => $data['nama_lengkap'],
                'username' => $data['username'],
                'email' => $data['email'],
                'telepon' => $data['telepon'] ?? null,
                'role' => config("menu.roles.{$data['role_key']}.db"),
                'cabang_id' => $data['cabang_id'] ?? null,
                'dc_id' => $data['dc_id'] ?? null,
                'status' => $data['status'],
            ]);

            if (! empty($data['password'])) {
                $lockedUser->password_hash = $data['password'];
            }

            $lockedUser->save();
        });

        return redirect()->route('admin.pengguna.index')->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Pengguna $pengguna): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);

        DB::transaction(function () use ($data, $pengguna): void {
            $lockedUser = Pengguna::query()->whereKey($pengguna->user_id)->lockForUpdate()->firstOrFail();

            if ($data['status'] === 'aktif') {
                $this->assertPlacementExists($lockedUser);
            }

            $this->assertBusinessRules([
                'role_key' => $lockedUser->role_key,
                'cabang_id' => $lockedUser->cabang_id,
                'dc_id' => $lockedUser->dc_id,
                'status' => $data['status'],
            ], $lockedUser);

            $lockedUser->update(['status' => $data['status']]);
        });

        return back()->with('success', 'Status pengguna berhasil diperbarui.');
    }

    public function resetPassword(Request $request, Pengguna $pengguna): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $pengguna->update(['password_hash' => $data['password']]);

        return back()->with('success', 'Password pengguna berhasil direset.');
    }

    public function destroy(Request $request, Pengguna $pengguna): RedirectResponse
    {
        if ($pengguna->user_id === $request->user()?->user_id) {
            return back()->withErrors([
                'delete' => 'Anda tidak dapat menghapus akun yang sedang digunakan untuk login.',
            ]);
        }

        return DB::transaction(function () use ($pengguna): RedirectResponse {
            $lockedUser = Pengguna::query()->whereKey($pengguna->user_id)->lockForUpdate()->firstOrFail();
            $this->assertLastAdminRemains($lockedUser, 'nonaktif', null);

            $references = [
                'transaksi_penjualan' => ['kasir_id'],
                'draft_instruksi' => ['dibuat_oleh', 'disetujui_oleh'],
                'surat_jalan' => ['diterbitkan_oleh'],
                'konfirmasi_penerimaan' => ['kepala_shift_id'],
                'waste_log' => ['kepala_shift_id'],
            ];

            foreach ($references as $table => $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, $column)
                        && DB::table($table)->where($column, $lockedUser->user_id)->exists()) {
                        return back()->withErrors([
                            'delete' => 'Pengguna memiliki riwayat transaksi atau operasional. Nonaktifkan akun sebagai gantinya.',
                        ]);
                    }
                }
            }

            $lockedUser->delete();

            return redirect()->route('admin.pengguna.index')->with('success', 'Pengguna berhasil dihapus.');
        });
    }

    /**
     * @param  array<string, string>  $prefill
     */
    private function formView(?Pengguna $pengguna = null, array $prefill = []): View
    {
        return view('admin.pengguna.form', [
            'pengguna' => $pengguna,
            'prefill' => $prefill,
            'branches' => Cabang::query()->orderBy('nama_cabang')->get(),
            'distributionCenters' => Dc::query()->orderBy('nama_dc')->get(),
            'roles' => config('menu.roles'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedUser(Request $request, ?Pengguna $pengguna = null): array
    {
        $nama = $request->input('nama_lengkap');
        $username = $request->input('username');
        $email = $request->input('email');
        $telepon = $request->input('telepon');

        $request->merge([
            'nama_lengkap' => is_string($nama) ? trim($nama) : $nama,
            'username' => is_string($username) ? mb_strtolower(trim($username)) : $username,
            'email' => is_string($email) ? mb_strtolower(trim($email)) : $email,
            'telepon' => is_string($telepon) && trim($telepon) !== '' ? trim($telepon) : null,
        ]);

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'min:3', 'max:150'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('pengguna', 'username')->ignore($pengguna?->user_id, 'user_id'),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('pengguna', 'email')->ignore($pengguna?->user_id, 'user_id'),
            ],
            'telepon' => ['nullable', 'string', 'max:20'],
            'role_key' => ['required', Rule::in(array_keys(config('menu.roles')))],
            'cabang_id' => [
                Rule::requiredIf(fn (): bool => in_array($request->input('role_key'), ['manager', 'ic', 'kasir'], true)),
                'nullable',
                'integer',
                Rule::exists('cabang', 'cabang_id'),
            ],
            'dc_id' => [
                Rule::requiredIf(fn (): bool => $request->input('role_key') === 'dc'),
                'nullable',
                'integer',
                Rule::exists('dc', 'dc_id'),
            ],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'password' => [$pengguna === null ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'username.regex' => 'Username hanya boleh berisi huruf kecil, angka, titik (.), garis bawah (_), atau tanda hubung (-).',
        ]);

        if ($data['role_key'] === 'admin') {
            $data['cabang_id'] = null;
            $data['dc_id'] = null;
        } elseif ($data['role_key'] === 'dc') {
            $data['cabang_id'] = null;
        } else {
            $data['dc_id'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertBusinessRules(array $data, ?Pengguna $pengguna = null): void
    {
        if (isset($data['username']) && is_string($data['username'])) {
            $usernameExists = Pengguna::query()
                ->whereRaw('LOWER(username) = ?', [mb_strtolower($data['username'])])
                ->when($pengguna !== null, fn (Builder $query) => $query->where('user_id', '!=', $pengguna->user_id))
                ->exists();

            if ($usernameExists) {
                throw ValidationException::withMessages([
                    'username' => 'Username sudah digunakan pengguna lain.',
                ]);
            }
        }

        if (isset($data['email']) && is_string($data['email'])) {
            $emailExists = Pengguna::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])
                ->when($pengguna !== null, fn (Builder $query) => $query->where('user_id', '!=', $pengguna->user_id))
                ->exists();

            if ($emailExists) {
                throw ValidationException::withMessages([
                    'email' => 'Email sudah digunakan pengguna lain.',
                ]);
            }
        }

        $this->assertLastAdminRemains(
            $pengguna,
            $data['status'],
            $data['role_key'],
        );

        if (($data['role_key'] ?? null) === 'manager' && ($data['status'] ?? null) === 'aktif') {
            $anotherManagerExists = Pengguna::query()
                ->where('role', config('menu.roles.manager.db'))
                ->where('status', 'aktif')
                ->where('cabang_id', $data['cabang_id'])
                ->when($pengguna !== null, fn (Builder $query) => $query->where('user_id', '!=', $pengguna->user_id))
                ->lockForUpdate()
                ->exists();

            if ($anotherManagerExists) {
                throw ValidationException::withMessages([
                    'cabang_id' => 'Cabang ini sudah memiliki manajer aktif.',
                ]);
            }
        }
    }

    private function assertPlacementExists(Pengguna $pengguna): void
    {
        if (in_array($pengguna->role_key, ['manager', 'ic', 'kasir'], true)) {
            $validBranch = $pengguna->cabang_id !== null
                && Cabang::query()->whereKey($pengguna->cabang_id)->exists();

            if (! $validBranch) {
                throw ValidationException::withMessages([
                    'status' => 'Pengguna belum memiliki cabang penempatan yang valid. Edit pengguna terlebih dahulu.',
                ]);
            }
        }

        if ($pengguna->role_key === 'dc') {
            $validDc = $pengguna->dc_id !== null
                && Dc::query()->whereKey($pengguna->dc_id)->exists();

            if (! $validDc) {
                throw ValidationException::withMessages([
                    'status' => 'Pengguna belum memiliki Distribution Center yang valid. Edit pengguna terlebih dahulu.',
                ]);
            }
        }
    }

    private function assertLastAdminRemains(?Pengguna $pengguna, string $status, ?string $roleKey): void
    {
        if ($pengguna === null || $pengguna->role_key !== 'admin' || $pengguna->status !== 'aktif') {
            return;
        }

        $remainsActiveAdmin = $status === 'aktif' && ($roleKey === null || $roleKey === 'admin');

        if (! $remainsActiveAdmin && Pengguna::query()
            ->where('role', config('menu.roles.admin.db'))
            ->where('status', 'aktif')
            ->lockForUpdate()
            ->count() <= 1) {
            throw ValidationException::withMessages([
                'status' => 'Admin aktif terakhir tidak dapat dinonaktifkan atau diganti rolenya.',
            ]);
        }
    }
}
