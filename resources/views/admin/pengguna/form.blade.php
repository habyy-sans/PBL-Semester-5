@extends('layouts.app')

@section('title', ($pengguna ? 'Edit ' : 'Tambah ').'Pengguna')
@section('description', 'Atur akun, role, dan penempatan operasional.')

@section('content')
<section class="page active admin-panel">
    <div class="admin-page-heading">
        <div>
            <h1>{{ $pengguna ? 'Edit Pengguna' : 'Tambah Pengguna' }}</h1>
            <p>Role DC ditempatkan ke DC; role cabang ditempatkan ke satu cabang.</p>
        </div>
        <a class="outline-button" href="{{ route('admin.pengguna.index') }}">Kembali</a>
    </div>
    @if ($errors->any())
        <div class="admin-notice error" role="alert">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif
    <div class="page-card admin-form-card">
        <form method="POST" action="{{ $pengguna ? route('admin.pengguna.update', $pengguna) : route('admin.pengguna.store') }}">
            @csrf
            @if ($pengguna) @method('PUT') @endif
            <div class="admin-form-grid">
                <div class="admin-form-field">
                    <label for="nama_lengkap">Nama lengkap <span class="required-mark">*</span></label>
                    <input id="nama_lengkap" name="nama_lengkap" value="{{ old('nama_lengkap', $pengguna?->nama_lengkap) }}" maxlength="150" required>
                    @error('nama_lengkap')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-form-field">
                    <label for="username">Username <span class="required-mark">*</span></label>
                    <input id="username" name="username" value="{{ old('username', $pengguna?->username) }}" maxlength="50" required>
                    @error('username')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-form-field">
                    <label for="email">Email <span class="required-mark">*</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email', $pengguna?->email) }}" maxlength="150" required>
                    @error('email')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-form-field">
                    <label for="telepon">Telepon</label>
                    <input id="telepon" name="telepon" value="{{ old('telepon', $pengguna?->telepon) }}" maxlength="20">
                    @error('telepon')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-form-field">
                    <label for="role_key">Role <span class="required-mark">*</span></label>
                    <select id="role_key" name="role_key" required>
                        <option value="">Pilih role</option>
                        @foreach ($roles as $key => $role)
                            <option value="{{ $key }}" @selected(old('role_key', $pengguna?->role_key) === $key)>{{ $role['label'] }}</option>
                        @endforeach
                    </select>
                    @error('role_key')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-form-field" id="branch-field">
                    <label for="cabang_id">Cabang <span class="required-mark">*</span></label>
                    <select id="cabang_id" name="cabang_id">
                        <option value="">Pilih cabang</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->cabang_id }}" @selected((string) old('cabang_id', $pengguna?->cabang_id) === (string) $branch->cabang_id)>{{ $branch->nama_cabang }}</option>
                        @endforeach
                    </select>
                    @error('cabang_id')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-form-field" id="dc-field">
                    <label for="dc_id">Distribution Center <span class="required-mark">*</span></label>
                    <select id="dc_id" name="dc_id">
                        <option value="">Pilih DC</option>
                        @foreach ($distributionCenters as $dc)
                            <option value="{{ $dc->dc_id }}" @selected((string) old('dc_id', $pengguna?->dc_id) === (string) $dc->dc_id)>{{ $dc->nama_dc }}</option>
                        @endforeach
                    </select>
                    @error('dc_id')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="admin-form-field">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="aktif" @selected(old('status', $pengguna?->status ?? 'aktif') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status', $pengguna?->status) === 'nonaktif')>Nonaktif</option>
                    </select>
                    @error('status')<span class="admin-field-error">{{ $message }}</span>@enderror
                </div>
                @unless ($pengguna)
                    <div class="admin-form-field">
                        <label for="password">Password awal <span class="required-mark">*</span></label>
                        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                        @error('password')<span class="admin-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="admin-form-field">
                        <label for="password_confirmation">Konfirmasi password <span class="required-mark">*</span></label>
                        <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                    </div>
                @endunless
            </div>
            <div class="admin-form-actions">
                <a class="outline-button" href="{{ route('admin.pengguna.index') }}">Batal</a>
                <button class="primary-button" type="submit">{{ $pengguna ? 'Simpan Perubahan' : 'Buat Pengguna' }}</button>
            </div>
        </form>
    </div>

    @if ($pengguna)
        <div class="page-card admin-form-card admin-password-card">
            <h2>Reset Password</h2>
            <p>Masukkan password baru. Password minimal 8 karakter.</p>
            <form method="POST" action="{{ route('admin.pengguna.password', $pengguna) }}">
                @csrf
                @method('PATCH')
                <div class="admin-form-grid">
                    <div class="admin-form-field">
                        <label for="reset-password">Password baru</label>
                        <input id="reset-password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="admin-form-field">
                        <label for="reset-password-confirmation">Konfirmasi password baru</label>
                        <input id="reset-password-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                    </div>
                </div>
                @error('password')<span class="admin-field-error">{{ $message }}</span>@enderror
                <div class="admin-form-actions"><button class="outline-button" type="submit">Reset Password</button></div>
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
    const updatePlacementFields = () => {
        const needsBranch = ['manager', 'ic', 'kasir'].includes(roleSelect.value);
        const needsDc = roleSelect.value === 'dc';
        branchField.hidden = !needsBranch;
        dcField.hidden = !needsDc;
        branchSelect.required = needsBranch;
        dcSelect.required = needsDc;
        branchSelect.disabled = !needsBranch;
        dcSelect.disabled = !needsDc;
    };
    roleSelect.addEventListener('change', updatePlacementFields);
    updatePlacementFields();
</script>
@endpush
