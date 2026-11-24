@extends('layouts.app')

@section('title', ($pengguna ? 'Edit ' : 'Tambah ').'Pengguna')
@section('description', 'Atur akun login, role sistem, dan penempatan lokasi operasional.')

@section('content')
@php
    $selectedRole = old('role_key', $pengguna?->role_key ?? ($prefill['role_key'] ?? ''));
    $selectedCabang = old('cabang_id', $pengguna?->cabang_id ?? ($prefill['cabang_id'] ?? ''));
    $selectedDc = old('dc_id', $pengguna?->dc_id ?? ($prefill['dc_id'] ?? ''));
@endphp

<section class="page active admin-panel">
    <div class="admin-subnav">
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('admin.pengguna.index') }}">Kelola Pengguna</a>
            <span aria-hidden="true">/</span>
            <span>{{ $pengguna ? 'Edit: '.$pengguna->nama_lengkap : 'Tambah Pengguna' }}</span>
        </nav>

        <a class="admin-btn-outline admin-btn-sm" href="{{ route('admin.pengguna.index') }}">
            <x-icon name="chevron-left" size="1em" />
            <span>Kembali</span>
        </a>
    </div>

    <x-admin.flash />

    <div class="admin-card admin-form-card">
        <div class="admin-form-header">
            <h3>{{ $pengguna ? 'Informasi Akun Pengguna' : 'Buat Akun Pengguna Baru' }}</h3>
            <p>Kolom bertanda <span class="required-mark">*</span> wajib diisi. Pengguna dapat masuk memakai username atau email.</p>
        </div>

        <form method="POST" action="{{ $pengguna ? route('admin.pengguna.update', $pengguna) : route('admin.pengguna.store') }}" data-prevent-double-submit>
            @csrf
            @if ($pengguna)
                @method('PUT')
            @endif

            <div class="admin-form-grid">
                <div class="admin-form-field">
                    <label for="nama_lengkap">Nama lengkap <span class="required-mark">*</span></label>
                    <input
                        id="nama_lengkap"
                        name="nama_lengkap"
                        value="{{ old('nama_lengkap', $pengguna?->nama_lengkap) }}"
                        maxlength="150"
                        placeholder="Contoh: Dewi Lestari"
                        aria-invalid="{{ $errors->has('nama_lengkap') ? 'true' : 'false' }}"
                        required
                    >
                    @error('nama_lengkap')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="admin-form-field">
                    <label for="username">Username <span class="required-mark">*</span></label>
                    <input
                        id="username"
                        name="username"
                        value="{{ old('username', $pengguna?->username) }}"
                        maxlength="50"
                        placeholder="Contoh: dewi.manager"
                        aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}"
                        required
                    >
                    <span class="admin-field-help">Huruf kecil, angka, titik, garis bawah, atau tanda hubung.</span>
                    @error('username')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="admin-form-field">
                    <label for="email">Email <span class="required-mark">*</span></label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email', $pengguna?->email) }}"
                        maxlength="150"
                        placeholder="nama@kopsync.id"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        required
                    >
                    @error('email')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="admin-form-field">
                    <label for="telepon">Telepon</label>
                    <input
                        id="telepon"
                        name="telepon"
                        value="{{ old('telepon', $pengguna?->telepon) }}"
                        maxlength="20"
                        placeholder="Contoh: 081234567890"
                        aria-invalid="{{ $errors->has('telepon') ? 'true' : 'false' }}"
                    >
                    @error('telepon')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="admin-form-field">
                    <label for="role_key">Role <span class="required-mark">*</span></label>
                    <select
                        id="role_key"
                        name="role_key"
                        aria-invalid="{{ $errors->has('role_key') ? 'true' : 'false' }}"
                        required
                    >
                        <option value="">Pilih role</option>
                        @foreach ($roles as $key => $role)
                            <option value="{{ $key }}" @selected($selectedRole === $key)>{{ $role['label'] }}</option>
                        @endforeach
                    </select>
                    @error('role_key')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="admin-form-field">
                    <label for="status">Status akses <span class="required-mark">*</span></label>
                    <select id="status" name="status" required>
                        <option value="aktif" @selected(old('status', $pengguna?->status ?? 'aktif') === 'aktif')>Aktif (dapat login)</option>
                        <option value="nonaktif" @selected(old('status', $pengguna?->status) === 'nonaktif')>Nonaktif (ditangguhkan)</option>
                    </select>
                    @error('status')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="admin-form-field full-width">
                    <div class="admin-placement-note" id="placement-note" role="note">
                        Pilih role pengguna untuk menentukan lokasi penempatan (Cabang, Distribution Center, atau seluruh sistem).
                    </div>
                </div>

                <div class="admin-form-field" id="branch-field">
                    <label for="cabang_id">Cabang penempatan <span class="required-mark">*</span></label>
                    <select
                        id="cabang_id"
                        name="cabang_id"
                        aria-invalid="{{ $errors->has('cabang_id') ? 'true' : 'false' }}"
                    >
                        <option value="">Pilih cabang</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->cabang_id }}" @selected((string) $selectedCabang === (string) $branch->cabang_id)>{{ $branch->nama_cabang }}</option>
                        @endforeach
                    </select>
                    <span class="admin-field-help" id="branch-help">1 cabang hanya boleh memiliki 1 Manajer Cabang aktif.</span>
                    @error('cabang_id')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="admin-form-field" id="dc-field">
                    <label for="dc_id">Distribution Center <span class="required-mark">*</span></label>
                    <select
                        id="dc_id"
                        name="dc_id"
                        aria-invalid="{{ $errors->has('dc_id') ? 'true' : 'false' }}"
                    >
                        <option value="">Pilih Distribution Center</option>
                        @foreach ($distributionCenters as $dc)
                            <option value="{{ $dc->dc_id }}" @selected((string) $selectedDc === (string) $dc->dc_id)>{{ $dc->nama_dc }}</option>
                        @endforeach
                    </select>
                    @error('dc_id')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>

                @unless ($pengguna)
                    <div class="admin-form-field">
                        <label for="password">Password awal <span class="required-mark">*</span></label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            minlength="8"
                            autocomplete="new-password"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            required
                        >
                        <span class="admin-field-help">Minimal 8 karakter.</span>
                        @error('password')<span class="admin-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="admin-form-field">
                        <label for="password_confirmation">Konfirmasi password <span class="required-mark">*</span></label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                @endunless
            </div>

            <div class="admin-form-actions">
                <a class="admin-btn-outline" href="{{ route('admin.pengguna.index') }}">Batal</a>
                <button class="admin-btn-primary" type="submit">{{ $pengguna ? 'Simpan Perubahan' : 'Buat Pengguna' }}</button>
            </div>
        </form>
    </div>

    @if ($pengguna)
        <div class="admin-card admin-form-card">
            <div class="admin-form-header">
                <h3>Reset Password</h3>
                <p>Masukkan password baru untuk {{ $pengguna->nama_lengkap }}. Password minimal 8 karakter.</p>
            </div>

            <form method="POST" action="{{ route('admin.pengguna.password', $pengguna) }}" data-prevent-double-submit>
                @csrf
                @method('PATCH')

                <div class="admin-form-grid">
                    <div class="admin-form-field">
                        <label for="reset-password">Password baru <span class="required-mark">*</span></label>
                        <input
                            id="reset-password"
                            name="password"
                            type="password"
                            minlength="8"
                            autocomplete="new-password"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            required
                        >
                        @error('password')<span class="admin-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="admin-form-field">
                        <label for="reset-password-confirmation">Konfirmasi password baru <span class="required-mark">*</span></label>
                        <input
                            id="reset-password-confirmation"
                            name="password_confirmation"
                            type="password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-btn-outline" type="submit">
                        <x-icon name="key-round" size="1em" />
                        <span>Reset Password</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</section>
@endsection

@push('scripts')
<script>
    const roleSelect = document.getElementById('role_key');
    const branchField = document.getElementById('branch-field');
    const dcField = document.getElementById('dc-field');
    const branchSelect = document.getElementById('cabang_id');
    const dcSelect = document.getElementById('dc_id');
    const placementNote = document.getElementById('placement-note');
    const branchHelp = document.getElementById('branch-help');

    const placementMessages = {
        admin: 'Admin Sistem memiliki akses ke seluruh sistem tanpa terikat pada satu cabang atau DC.',
        dc: 'Akun Distribution Center wajib ditempatkan ke salah satu fasilitas gudang DC.',
        manager: 'Manajer Cabang wajib terikat ke 1 cabang. Setiap cabang maksimal memiliki 1 manajer aktif.',
        ic: 'Kepala Shift (IC) wajib terikat ke 1 cabang operasional untuk menerima barang dan mencatat waste.',
        kasir: 'Kasir wajib terikat ke 1 cabang operasional untuk melayani transaksi POS.',
    };

    const updatePlacementFields = () => {
        const role = roleSelect.value;
        const needsBranch = ['manager', 'ic', 'kasir'].includes(role);
        const needsDc = role === 'dc';

        branchField.hidden = !needsBranch;
        dcField.hidden = !needsDc;
        branchSelect.required = needsBranch;
        dcSelect.required = needsDc;
        branchSelect.disabled = !needsBranch;
        dcSelect.disabled = !needsDc;

        branchHelp.hidden = role !== 'manager';
        placementNote.textContent = placementMessages[role]
            || 'Pilih role pengguna untuk menentukan lokasi penempatan (Cabang, Distribution Center, atau seluruh sistem).';
    };

    roleSelect.addEventListener('change', updatePlacementFields);
    updatePlacementFields();

    document.querySelectorAll('[data-prevent-double-submit]').forEach((form) => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
            }
        });
    });
</script>
@endpush
