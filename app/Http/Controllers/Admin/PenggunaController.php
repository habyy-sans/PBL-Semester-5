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

        foreach (['role', 'cabang_id', 'dc_id', 'status'] as $filter) {
            $value = $request->query($filter);

            if (is_string($value) && $value !== '') {
                if ($filter === 'role' && isset(config('menu.roles')[$value])) {
                    $value = config("menu.roles.{$value}.db");
                }

                $query->where($filter, $value);
            }
        }

        return view('admin.pengguna.index', [
            'users' => $query->orderBy('nama_lengkap')->paginate(12)->withQueryString(),
            'branches' => Cabang::query()->orderBy('nama_cabang')->get(),
            'distributionCenters' => Dc::query()->orderBy('nama_dc')->get(),
            'roles' => config('menu.roles'),
            'keyword' => $keyword,
            'filters' => $request->only(['role', 'cabang_id', 'dc_id', 'status']),
            'totals' => [
                'all' => Pengguna::query()->count(),
                'active' => Pengguna::query()->where('status', 'aktif')->count(),
                'inactive' => Pengguna::query()->where('status', 'nonaktif')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return $this->formView();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedUser($request);
        $this->assertBusinessRules($data);

        Pengguna::query()->create([
            'nama_lengkap' => trim($data['nama_lengkap']),
            'username' => mb_strtolower(trim($data['username'])),
            'email' => mb_strtolower(trim($data['email'])),
            'password_hash' => $data['password'],
            'telepon' => $data['telepon'] ?? null,
            'role' => config("menu.roles.{$data['role_key']}.db"),
            'cabang_id' => $data['cabang_id'] ?? null,
            'dc_id' => $data['dc_id'] ?? null,
            'status' => $data['status'],
        ]);

        return redirect()->route('admin.pengguna.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(Pengguna $pengguna): View
    {
        return $this->formView($pengguna);
    }

    public function update(Request $request, Pengguna $pengguna): RedirectResponse
    {
        $data = $this->validatedUser($request, $pengguna);
        $this->assertBusinessRules($data, $pengguna);

        $pengguna->fill([
            'nama_lengkap' => trim($data['nama_lengkap']),
            'username' => mb_strtolower(trim($data['username'])),
            'email' => mb_strtolower(trim($data['email'])),
            'telepon' => $data['telepon'] ?? null,
            'role' => config("menu.roles.{$data['role_key']}.db"),
            'cabang_id' => $data['cabang_id'] ?? null,
            'dc_id' => $data['dc_id'] ?? null,
            'status' => $data['status'],
        ]);
        $pengguna->save();

        return redirect()->route('admin.pengguna.index')->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Pengguna $pengguna): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);

        $this->assertBusinessRules([
            'role_key' => $pengguna->role_key,
            'cabang_id' => $pengguna->cabang_id,
            'dc_id' => $pengguna->dc_id,
            'status' => $data['status'],
        ], $pengguna);

        $pengguna->update(['status' => $data['status']]);

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

    public function destroy(Pengguna $pengguna): RedirectResponse
    {
        $this->assertLastAdminRemains($pengguna, 'nonaktif', null);

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
                    && DB::table($table)->where($column, $pengguna->user_id)->exists()) {
                    return back()->withErrors([
                        'delete' => 'Pengguna memiliki riwayat transaksi atau operasional. Nonaktifkan akun sebagai gantinya.',
                    ]);
                }
            }
        }

        $pengguna->delete();

        return redirect()->route('admin.pengguna.index')->with('success', 'Pengguna berhasil dihapus.');
    }

    private function formView(?Pengguna $pengguna = null): View
    {
        return view('admin.pengguna.form', [
            'pengguna' => $pengguna,
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
        $username = $request->input('username');
        $email = $request->input('email');

        $request->merge([
            'username' => is_string($username) ? mb_strtolower(trim($username)) : $username,
            'email' => is_string($email) ? mb_strtolower(trim($email)) : $email,
        ]);

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'min:3', 'max:150'],
            'username' => [
                'required',
                'string',
                'max:50',
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
        ]);

        $data['nama_lengkap'] = trim($data['nama_lengkap']);
        $data['username'] = mb_strtolower(trim($data['username']));
        $data['email'] = mb_strtolower(trim($data['email']));

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
                ->exists();

            if ($anotherManagerExists) {
                throw ValidationException::withMessages([
                    'cabang_id' => 'Cabang ini sudah memiliki manajer aktif.',
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
            ->count() <= 1) {
            throw ValidationException::withMessages([
                'status' => 'Admin aktif terakhir tidak dapat dinonaktifkan atau diganti rolenya.',
            ]);
        }
    }
}
