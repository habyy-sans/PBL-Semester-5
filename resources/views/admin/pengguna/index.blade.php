@extends('layouts.app')

@section('title', 'Kelola Pengguna')
@section('description', 'Kelola akun, role, penempatan, dan status akses.')

@section('content')
<section class="page active admin-panel">
    <div class="admin-page-heading">
        <div>
            <h1>Kelola Pengguna</h1>
            <p>Kelola akses admin, cabang, dan distribution center.</p>
        </div>
        <a class="primary-button" href="{{ route('admin.pengguna.create') }}">+ Tambah Pengguna</a>
    </div>

    @if (session('success'))
        <div class="admin-notice success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="admin-notice error" role="alert">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="stats-grid admin-user-stats">
        <div class="stat-card"><div class="stat-icon">▣</div><div class="stat-content"><span class="stat-label">Total Pengguna</span><strong>{{ number_format($totals['all']) }}</strong></div></div>
        <div class="stat-card"><div class="stat-icon">✓</div><div class="stat-content"><span class="stat-label">Aktif</span><strong>{{ number_format($totals['active']) }}</strong></div></div>
        <div class="stat-card"><div class="stat-icon">—</div><div class="stat-content"><span class="stat-label">Nonaktif</span><strong>{{ number_format($totals['inactive']) }}</strong></div></div>
    </div>

    <div class="page-card admin-data-card">
        <form class="table-toolbar admin-toolbar" method="GET" action="{{ route('admin.pengguna.index') }}">
            <label class="admin-search-field">
                <span class="sr-only">Cari pengguna</span>
                <input class="search-input" type="search" name="q" value="{{ $keyword }}" placeholder="Cari nama, username, atau email...">
            </label>
            <select class="select admin-filter" name="role" aria-label="Filter role">
                <option value="">Semua Role</option>
                @foreach ($roles as $key => $role)<option value="{{ $key }}" @selected(($filters['role'] ?? '') === $key)>{{ $role['label'] }}</option>@endforeach
            </select>
            <select class="select admin-filter" name="cabang_id" aria-label="Filter cabang">
                <option value="">Semua Cabang</option>
                @foreach ($branches as $branch)<option value="{{ $branch->cabang_id }}" @selected(($filters['cabang_id'] ?? '') == $branch->cabang_id)>{{ $branch->nama_cabang }}</option>@endforeach
            </select>
            <select class="select admin-filter" name="dc_id" aria-label="Filter DC">
                <option value="">Semua DC</option>
                @foreach ($distributionCenters as $dc)<option value="{{ $dc->dc_id }}" @selected(($filters['dc_id'] ?? '') == $dc->dc_id)>{{ $dc->nama_dc }}</option>@endforeach
            </select>
            <select class="select admin-filter" name="status" aria-label="Filter status">
                <option value="">Semua Status</option>
                <option value="aktif" @selected(($filters['status'] ?? '') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(($filters['status'] ?? '') === 'nonaktif')>Nonaktif</option>
            </select>
            <button class="outline-button" type="submit">Terapkan</button>
            @if (request()->query())<a class="admin-clear-filter" href="{{ route('admin.pengguna.index') }}">Reset</a>@endif
            <span class="table-meta">{{ $users->total() }} pengguna</span>
        </form>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead><tr><th>Pengguna</th><th>Role</th><th>Penempatan</th><th>Telepon</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td><strong>{{ $user->nama_lengkap }}</strong><small class="admin-secondary-line">{{ '@'.$user->username }} · {{ $user->email }}</small></td>
                            <td><span class="status info">{{ $roles[$user->role_key]['label'] ?? $user->role }}</span></td>
                            <td>{{ $user->cabang?->nama_cabang ?? $user->dc?->nama_dc ?? 'Seluruh sistem' }}</td>
                            <td>{{ $user->telepon ?: '—' }}</td>
                            <td><span class="status {{ $user->status === 'aktif' ? 'success' : 'danger' }}">{{ ucfirst($user->status) }}</span></td>
                            <td>
                                <div class="admin-row-actions">
                                    <a class="outline-button admin-small-button" href="{{ route('admin.pengguna.edit', $user) }}">Edit</a>
                                    <button
                                        class="text-button"
                                        type="button"
                                        data-status-url="{{ route('admin.pengguna.status', $user) }}"
                                        data-status="{{ $user->status === 'aktif' ? 'nonaktif' : 'aktif' }}"
                                        data-user-name="{{ $user->nama_lengkap }}"
                                    >{{ $user->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    <button class="text-button danger-text" type="button" data-delete-url="{{ route('admin.pengguna.destroy', $user) }}" data-delete-name="{{ $user->nama_lengkap }}">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="page-empty">Pengguna tidak ditemukan.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="admin-pagination">{{ $users->links() }}</div>
    </div>
</section>

<dialog class="admin-confirm-dialog" id="user-action-dialog">
    <form method="POST" id="user-action-form">
        @csrf
        <input type="hidden" name="_method" id="user-action-method" value="PATCH">
        <input type="hidden" name="status" id="user-action-status">
        <h2 id="user-action-title">Konfirmasi</h2>
        <p id="user-action-description"></p>
        <div class="admin-dialog-actions">
            <button class="outline-button" type="button" data-user-dialog-close>Batal</button>
            <button class="primary-button admin-danger-button" type="submit" id="user-action-submit">Lanjutkan</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
    const actionDialog = document.getElementById('user-action-dialog');
    const actionForm = document.getElementById('user-action-form');
    const actionMethod = document.getElementById('user-action-method');
    const actionStatus = document.getElementById('user-action-status');
    const actionTitle = document.getElementById('user-action-title');
    const actionDescription = document.getElementById('user-action-description');
    const actionSubmit = document.getElementById('user-action-submit');
    document.querySelectorAll('[data-status-url]').forEach((button) => {
        button.addEventListener('click', () => {
            actionForm.action = button.dataset.statusUrl;
            actionMethod.value = 'PATCH';
            actionStatus.value = button.dataset.status;
            actionTitle.textContent = button.dataset.status === 'aktif' ? 'Aktifkan pengguna?' : 'Nonaktifkan pengguna?';
            actionDescription.textContent = `Status ${button.dataset.userName} akan diubah.`;
            actionSubmit.textContent = 'Ya, lanjutkan';
            actionDialog.showModal();
        });
    });
    document.querySelectorAll('[data-delete-url]').forEach((button) => {
        button.addEventListener('click', () => {
            actionForm.action = button.dataset.deleteUrl;
            actionMethod.value = 'DELETE';
            actionStatus.value = '';
            actionTitle.textContent = 'Hapus pengguna?';
            actionDescription.textContent = `Akun ${button.dataset.deleteName} akan dihapus. Jika memiliki riwayat operasional, nonaktifkan akun sebagai gantinya.`;
            actionSubmit.textContent = 'Ya, hapus';
            actionDialog.showModal();
        });
    });
    document.querySelector('[data-user-dialog-close]')?.addEventListener('click', () => actionDialog.close());
</script>
@endpush
