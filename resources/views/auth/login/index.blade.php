@extends('layouts.auth')

@section('title', 'Login')

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

        <div class="login-card" x-data="{ showPassword: false }" x-init="$refs.identifier.focus()">

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

            <!-- ERROR UMUM (validasi server) -->
            @if ($errors->any())
                <div class="login-alert" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form class="login-form" method="POST" action="{{ route('login.attempt') }}" novalidate>

                @csrf

                <!-- EMAIL / USERNAME -->
                <div class="login-field">
                    <label class="login-label" for="identifier">Email atau Username</label>

                    <input
                        id="identifier"
                        name="identifier"
                        type="text"
                        class="login-input @error('identifier') has-error @enderror"
                        placeholder="Masukkan email atau username"
                        autocomplete="username"
                        value="{{ old('identifier') }}"
                        x-ref="identifier"
                    >

                    @error('identifier')
                        <p class="login-error">{{ $message }}</p>
                    @enderror
                </div>

                <!-- PASSWORD -->
                <div class="login-field">
                    <label class="login-label" for="password">Password</label>

                    <div class="login-password">
                        <input
                            id="password"
                            name="password"
                            :type="showPassword ? 'text' : 'password'"
                            class="login-input @error('password') has-error @enderror"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                        >

                        <button
                            type="button"
                            class="login-toggle"
                            @click="showPassword = !showPassword"
                            x-text="showPassword ? 'Sembunyikan' : 'Lihat'"
                        >Lihat</button>
                    </div>

                    @error('password')
                        <p class="login-error">{{ $message }}</p>
                    @enderror
                </div>

                <!-- OPSI -->
                <div class="login-options">
                    <label class="login-check">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        Ingat saya
                    </label>

                    <span>Lupa password? Hubungi Admin</span>
                </div>

                <!-- SUBMIT -->
                <button type="submit" class="login-submit">Masuk</button>

            </form>

            <p class="login-footer">
                Belum punya akun? <strong>Hubungi Admin</strong>
            </p>

        </div>

    </main>

</div>

@endsection