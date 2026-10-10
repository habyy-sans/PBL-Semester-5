@extends('layouts.auth')

@section('title', 'Login')

@php
    // DEMO: akun contoh untuk testing frontend (hapus saat backend jadi)
    $demoAccounts = collect(config('menu.roles'))->map(fn ($meta, $key) => [
        'role'     => $key,
        'label'    => $meta['label'],
        'username' => $key,
        'email'    => $key . '@kopsync.test',
        'password' => 'password123',
        'url'      => route($meta['home']),
    ])->values();
@endphp

@section('content')

<div class="login-page">

    <!-- =====================================================
         PANEL KIRI
    ====================================================== -->

    <aside class="login-brand">

        <div class="login-brand-top">
            <div class="logo-box">K</div>

            <div>
                <h1>KopSync</h1>
                <span>POS Management</span>
            </div>
        </div>

        <div class="login-brand-body">
            <h2>Kelola kedai kopi lebih rapi, stok lebih akurat.</h2>

            <p>
                Satu sistem untuk kasir, stok cabang, distribusi dari DC,
                pencatatan waste, dan monitoring operasional.
            </p>
        </div>

        <small>Kopi Studio 24 · Sistem POS & Manajemen Pasokan Multi-Cabang</small>

    </aside>


    <!-- =====================================================
         PANEL KANAN (FORM)
    ====================================================== -->

    <main class="login-panel">

        <div class="login-card" x-data="loginForm()" x-init="$refs.identifier.focus()">

            <!-- brand (hanya tampil di HP) -->
            <div class="login-card-brand">
                <div class="logo-box" style="background: var(--brown); color: white;">K</div>

                <div>
                    <strong>KopSync</strong>
                    <span>POS Management</span>
                </div>
            </div>

            <h2 class="login-title">Masuk ke akun</h2>
            <p class="login-subtitle">Gunakan akun yang diberikan oleh Admin.</p>

            <!-- ERROR UMUM -->
            <div class="login-alert" x-show="errors.general" x-text="errors.general" x-cloak role="alert"></div>

            <!-- DEMO: saat backend jadi, hapus @submit.prevent dan tambahkan method="POST" action="..." + @csrf -->
            <form class="login-form" @submit.prevent="submit()" novalidate>

                <!-- EMAIL / USERNAME -->
                <div class="login-field">
                    <label class="login-label" for="identifier">Email atau Username</label>

                    <input
                        id="identifier"
                        name="identifier"
                        type="text"
                        class="login-input"
                        :class="{ 'has-error': errors.identifier }"
                        placeholder="Masukkan email atau username"
                        autocomplete="username"
                        x-ref="identifier"
                        x-model="identifier"
                        @input="errors.identifier = ''; errors.general = ''"
                        :aria-invalid="errors.identifier ? 'true' : 'false'"
                    >

                    <p class="login-error" x-show="errors.identifier" x-text="errors.identifier" x-cloak></p>
                </div>

                <!-- PASSWORD -->
                <div class="login-field">
                    <label class="login-label" for="password">Password</label>

                    <div class="login-password">
                        <input
                            id="password"
                            name="password"
                            :type="showPassword ? 'text' : 'password'"
                            class="login-input"
                            :class="{ 'has-error': errors.password }"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            x-model="password"
                            @input="errors.password = ''; errors.general = ''"
                            :aria-invalid="errors.password ? 'true' : 'false'"
                        >

                        <button
                            type="button"
                            class="login-toggle"
                            @click="showPassword = !showPassword"
                            x-text="showPassword ? 'Sembunyikan' : 'Lihat'"
                        >Lihat</button>
                    </div>

                    <p class="login-error" x-show="errors.password" x-text="errors.password" x-cloak></p>
                </div>

                <!-- OPSI -->
                <div class="login-options">
                    <label class="login-check">
                        <input type="checkbox" name="remember" x-model="remember">
                        Ingat saya
                    </label>

                    <span>Lupa password? Hubungi Admin</span>
                </div>

                <!-- SUBMIT -->
                <button
                    type="submit"
                    class="login-submit"
                    :disabled="loading"
                    x-text="loading ? 'Memproses...' : 'Masuk'"
                >Masuk</button>

            </form>

            <p class="login-footer">
                Belum punya akun? <strong>Hubungi Admin</strong>
            </p>

        </div>

    </main>

</div>

@endsection

@push('scripts')
<script>
    // DEMO: seluruh fungsi ini diganti oleh login backend (Laravel) nanti.
    function loginForm() {

        return {

            identifier: '',
            password: '',
            remember: false,
            showPassword: false,
            loading: false,

            errors: {
                identifier: '',
                password: '',
                general: '',
            },

            accounts: @json($demoAccounts),


            fill(account) {

                this.identifier = account.username;
                this.password = account.password;

                this.errors = { identifier: '', password: '', general: '' };
            },


            validate() {

                this.errors = { identifier: '', password: '', general: '' };

                if (!this.identifier.trim()) {
                    this.errors.identifier = 'Email atau username wajib diisi.';
                }

                if (!this.password) {
                    this.errors.password = 'Password wajib diisi.';
                } else if (this.password.length < 6) {
                    this.errors.password = 'Password minimal 6 karakter.';
                }

                return !this.errors.identifier && !this.errors.password;
            },


            submit() {

                if (!this.validate()) {
                    return;
                }

                this.loading = true;

                setTimeout(() => {

                    const input = this.identifier.trim().toLowerCase();

                    const account = this.accounts.find(item =>
                        (item.username === input || item.email === input)
                        && item.password === this.password
                    );

                    if (!account) {

                        this.errors.general = 'Email/username atau password salah.';
                        this.loading = false;

                        return;
                    }

                    window.location.href = account.url;

                }, 500);
            },

        };
    }
</script>
@endpush