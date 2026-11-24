@extends('layouts.app')

@section('title', 'Kelola Pengguna')
@section('description', 'Kelola akun pengguna, role, penempatan operasional, dan status akses login.')

@push('header_custom')
    <a class="admin-btn-primary" href="{{ route('admin.pengguna.create') }}">
        <x-icon name="plus" size="1em" />
        <span>Tambah Pengguna</span>
    </a>
@endpush

@section('content')
<section class="page active admin-panel">
    <x-admin.flash />

    <div class="admin-kpi-grid cols-4">
        <div class="admin-stat-card">
            <div class="admin-stat-icon"><x-icon name="users" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Total Pengguna</span>
                <strong class="admin-stat-value tabular-nums">{{ number_format($totals['all'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub">Seluruh role terdaftar</small>
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-icon"><x-icon name="circle-check" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Pengguna Aktif</span>
                <strong class="admin-stat-value tabular-nums">{{ number_format($totals['active'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub">Dapat masuk ke sistem</small>
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-icon"><x-icon name="user-x" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Pengguna Nonaktif</span>
                <strong class="admin-stat-value tabular-nums">{{ number_format($totals['inactive'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub">Akses login ditangguhkan</small>
            </div>
        </div>

        @if ($totals['no_manager'] > 0)
            @php $firstBranch = $branchesWithoutManager->first(); @endphp
            <a class="admin-stat-card is-alert" href="{{ route('admin.pengguna.create', ['role_key' => 'manager', 'cabang_id' => $firstBranch?->cabang_id]) }}">
                <div class="admin-stat-icon"><x-icon name="store" size="1.25em" /></div>
                <div class="admin-stat-body">
                    <span class="admin-stat-label">Cabang Tanpa Manajer</span>
                    <strong class="admin-stat-value tabular-nums">{{ number_format($totals['no_manager'], 0, ',', '.') }}</strong>
                    <small class="admin-stat-sub">Klik untuk tetapkan manajer</small>
                </div>
            </a>
        @else
            <div class="admin-stat-card">
                <div class="admin-stat-icon"><x-icon name="store" size="1.25em" /></div>
                <div class="admin-stat-body">
                    <span class="admin-stat-label">Cabang Tanpa Manajer</span>
                    <strong class="admin-stat-value tabular-nums">0</strong>
                    <small class="admin-stat-sub">Semua cabang terisi</small>
                </div>
            </div>
        @endif
    </div>

    <div class="admin-card">
        <form class="admin-toolbar" method="GET" action="{{ route('admin.pengguna.index') }}" data-auto-filter-form>
            <div class="admin-search-box">
                <x-icon name="search" size="1em" class="admin-search-icon" />
                <label class="sr-only" for="pengguna-search">Cari pengguna</label>
                <input
                    id="pengguna-search"
                    class="admin-input"
                    type="search"
                    name="q"
                    value="{{ $keyword }}"
                    placeholder="Cari nama, username, atau email..."
                >
            </div>

            <select class="admin-select" name="role" aria-label="Filter role" data-auto-submit>
                <option value="">Semua Role</option>
                @foreach ($roles as $key => $role)
                    <option value="{{ $key }}" @selected(($filters['role'] ?? '') === $key)>{{ $role['label'] }}</option>
                @endforeach
            </select>

            <select class="admin-select" name="cabang_id" aria-label="Filter cabang" data-auto-submit>
                <option value="">Semua Cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->cabang_id }}" @selected(($filters['cabang_id'] ?? '') === (string) $branch->cabang_id)>{{ $branch->nama_cabang }}</option>
                @endforeach
            </select>

            <select class="admin-select" name="dc_id" aria-label="Filter DC" data-auto-submit>
                <option value="">Semua DC</option>
                @foreach ($distributionCenters as $dc)
                    <option value="{{ $dc->dc_id }}" @selected(($filters['dc_id'] ?? '') === (string) $dc->dc_id)>{{ $dc->nama_dc }}</option>
                @endforeach
            </select>

            <select class="admin-select" name="status" aria-label="Filter status" data-auto-submit>
                <option value="">Semua Status</option>
                <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
            </select>

            <button class="admin-btn-outline" type="submit">Cari</button>

            @if ($hasActiveFilters)
                <a class="admin-btn-ghost" href="{{ route('admin.pengguna.index') }}">Reset</a>
            @endif

            <span class="admin-toolbar-meta tabular-nums">{{ number_format($users->total(), 0, ',', '.') }} pengguna</span>
        </form>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Pengguna</th>
                        <th scope="col">Role</th>
                        <th scope="col">Penempatan</th>
                        <th scope="col">Telepon</th>
                        <th scope="col">Status</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <span class="admin-cell-primary">{{ $user->nama_lengkap }}</span>
                                <span class="admin-cell-sub">{{ '@'.$user->username }} · {{ $user->email }}</span>
                            </td>
                            <td>
                                <x-admin.status-badge variant="info">{{ $roles[$user->role_key]['label'] ?? $user->role }}</x-admin.status-badge>
                            </td>
                            <td>{{ $user->cabang?->nama_cabang ?? $user->dc?->nama_dc ?? 'Seluruh sistem' }}</td>
                            <td class="tabular-nums">{{ $user->telepon ?: '-' }}</td>
                            <td>
                                @if ($user->status === 'aktif')
                                    <x-admin.status-badge variant="success" icon="circle-check">Aktif</x-admin.status-badge>
                                @else
                                    <x-admin.status-badge variant="danger" icon="circle-x">Nonaktif</x-admin.status-badge>
                                @endif
                            </td>
                            <td>
                                <div class="admin-row-actions">
                                    <a class="admin-btn-outline admin-btn-sm" href="{{ route('admin.pengguna.edit', $user) }}">Edit</a>
                                    <button
                                        class="admin-btn-ghost admin-btn-sm"
                                        type="button"
                                        data-status-url="{{ route('admin.pengguna.status', $user) }}"
                                        data-status="{{ $user->status === 'aktif' ? 'nonaktif' : 'aktif' }}"
                                        data-user-name="{{ $user->nama_lengkap }}"
                                    >{{ $user->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    @if ($user->user_id !== auth()->id())
                                        <button
                                            class="admin-btn-ghost admin-btn-sm is-danger"
                                            type="button"
                                            data-delete-url="{{ route('admin.pengguna.destroy', $user) }}"
                                            data-delete-name="{{ $user->nama_lengkap }}"
                                        >Hapus</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                @if ($hasActiveFilters)
                                    <x-admin.empty-state
                                        icon="search"
                                        title="Tidak ada pengguna yang cocok"
                                        description="Ubah kata kunci pencarian atau reset filter untuk melihat seluruh akun."
                                        :actionUrl="route('admin.pengguna.index')"
                                        actionLabel="Reset filter"
                                    />
                                @else
                                    <x-admin.empty-state
                                        icon="users"
                                        title="Belum ada data pengguna"
                                        description="Tambahkan akun pengguna agar staf cabang dan DC dapat login sesuai perannya."
                                        :actionUrl="route('admin.pengguna.create')"
                                        actionLabel="Tambah Pengguna"
                                    />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $users->links('components.admin.pagination') }}
    </div>
</section>

<x-admin.confirm-dialog
    id="user-action-dialog"
    formId="user-action-form"
    titleId="user-action-title"
    descId="user-action-description"
    defaultTitle="Konfirmasi tindakan"
>
    <x-slot:hiddenFields>
        <input type="hidden" name="status" id="user-action-status">
    </x-slot:hiddenFields>
    Status pengguna akan diperbarui.
</x-admin.confirm-dialog>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => select.form?.submit());
    });

    const actionDialog = document.getElementById('user-action-dialog');
    const actionForm = document.getElementById('user-action-form');
    const actionMethod = document.getElementById('user-action-dialog-method');
    const actionStatus = document.getElementById('user-action-status');
    const actionTitle = document.getElementById('user-action-title');
    const actionDescription = document.getElementById('user-action-description');
    const actionSubmit = document.getElementById('user-action-dialog-submit');

    document.querySelectorAll('[data-status-url]').forEach((button) => {
        button.addEventListener('click', () => {
            const activating = button.dataset.status === 'aktif';
            actionForm.action = button.dataset.statusUrl;
            actionMethod.value = 'PATCH';
            actionStatus.value = button.dataset.status;
            actionTitle.textContent = activating ? 'Aktifkan pengguna?' : 'Nonaktifkan pengguna?';
            actionDescription.textContent = activating
                ? `Akun ${button.dataset.userName} akan diaktifkan kembali sehingga dapat login ke sistem.`
                : `Akun ${button.dataset.userName} tidak akan bisa login sampai diaktifkan kembali.`;
            actionSubmit.textContent = activating ? 'Ya, aktifkan' : 'Ya, nonaktifkan';
            actionSubmit.className = activating ? 'admin-btn-primary' : 'admin-btn-danger';
            actionSubmit.disabled = false;
            actionDialog.showModal();
        });
    });

    document.querySelectorAll('[data-delete-url]').forEach((button) => {
        button.addEventListener('click', () => {
            actionForm.action = button.dataset.deleteUrl;
            actionMethod.value = 'DELETE';
            actionStatus.value = '';
            actionTitle.textContent = 'Hapus pengguna?';
            actionDescription.textContent = `Akun ${button.dataset.deleteName} akan dihapus permanen. Jika memiliki riwayat transaksi atau operasional, nonaktifkan akun sebagai gantinya.`;
            actionSubmit.textContent = 'Ya, hapus';
            actionSubmit.className = 'admin-btn-danger';
            actionSubmit.disabled = false;
            actionDialog.showModal();
        });
    });
</script>
@endpush
