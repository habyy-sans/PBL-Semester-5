@extends('layouts.auth')

@section('title', 'Login')

@section('content')

{{-- Kalau halaman dimuat ulang karena login gagal, langsung tampilkan form agar pesan error terlihat --}}
@php($startForm = (bool) ($errors->any() || old('identifier')))

<noscript>
    <style>
        .login-page .login-brand { clip-path: inset(0 calc((1 - var(--cr)) * 100%) 0 0); }
        .login-page .login-brand-body { transform: none !important; }
        .login-page .login-enter { display: none; }
    </style>
</noscript>

<div
    class="login-page {{ $startForm ? 'is-form' : '' }}"
    data-start="{{ $startForm ? 'form' : 'intro' }}"
    :class="{ 'is-form': stage === 'form', 'is-revealing': revealing }"
    x-data="loginPage({{ $startForm ? 'true' : 'false' }})"
    x-init="start()"
>

    <!-- sapaan + jam: tetap di pojok kanan atas, warnanya menyesuaikan latar -->
    <div class="login-clock" x-cloak>
        <strong x-text="greeting"></strong>
        <span x-text="dateText + ' · ' + timeText"></span>
    </div>

    <!-- =====================================================
         PANEL KIRI  (pratinjau sistem yang bergerak)
    ====================================================== -->

    <aside class="login-brand" x-ref="brand" @mousemove="onMoveLeft($event)" @mouseleave="onLeaveLeft($event)">

        <div class="login-brand-inner">

            <!-- latar: titik halus + bercak warna yang bergerak pelan -->
        <div class="login-dots" aria-hidden="true"></div>

        <div class="login-blobs" aria-hidden="true">
            <i class="login-blob login-blob--a"></i>
            <i class="login-blob login-blob--b"></i>
        </div>

        <div class="login-brand-top">
            <div class="logo-box">
                <img src="{{ asset('image/logo_kopsync.png') }}" alt="Logo KopSync" width="40" height="40">
            </div>

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

            <!-- ALUR OPERASIONAL (berlaku untuk semua peran) -->
            <div class="login-flow"
                 @mouseenter="paused = true" @mouseleave="paused = false"
                 @focusin="paused = true" @focusout="paused = false">

                <div class="login-flow-nodes" role="tablist" aria-label="Alur operasional KopSync">

                    <div class="login-flow-line" aria-hidden="true">
                        <div class="login-flow-fill" :style="'width:' + (flow / 4 * 100) + '%'"></div>
                    </div>

                    <button type="button" class="login-node" role="tab"
                            :class="{ 'is-done': flow > 0, 'is-now': flow === 0 }"
                            :aria-selected="(flow === 0).toString()" @click="selectFlow(0)">
                        <span class="login-node-dot">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"></path>
                                <circle cx="7" cy="18" r="2"></circle>
                                <circle cx="17" cy="18" r="2"></circle>
                            </svg>
                        </span>
                        <span class="login-node-label">DC</span>
                    </button>

                    <button type="button" class="login-node" role="tab"
                            :class="{ 'is-done': flow > 1, 'is-now': flow === 1 }"
                            :aria-selected="(flow === 1).toString()" @click="selectFlow(1)">
                        <span class="login-node-dot">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 8l-9-5-9 5v8l9 5 9-5z"></path>
                                <path d="M3 8l9 5 9-5M12 13v8"></path>
                            </svg>
                        </span>
                        <span class="login-node-label">Stok cabang</span>
                    </button>

                    <button type="button" class="login-node" role="tab"
                            :class="{ 'is-done': flow > 2, 'is-now': flow === 2 }"
                            :aria-selected="(flow === 2).toString()" @click="selectFlow(2)">
                        <span class="login-node-dot">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"></path>
                                <path d="M9 8h6M9 12h6"></path>
                            </svg>
                        </span>
                        <span class="login-node-label">Kasir</span>
                    </button>

                    <button type="button" class="login-node" role="tab"
                            :class="{ 'is-done': flow > 3, 'is-now': flow === 3 }"
                            :aria-selected="(flow === 3).toString()" @click="selectFlow(3)">
                        <span class="login-node-dot">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 7h16M10 11v6M14 11v6"></path>
                                <path d="M6 7l1 13h10l1-13M9 7V4h6v3"></path>
                            </svg>
                        </span>
                        <span class="login-node-label">Waste</span>
                    </button>

                    <button type="button" class="login-node" role="tab"
                            :class="{ 'is-done': flow > 4, 'is-now': flow === 4 }"
                            :aria-selected="(flow === 4).toString()" @click="selectFlow(4)">
                        <span class="login-node-dot">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 20V4M4 20h16"></path>
                                <path d="M8 15l4-4 3 3 5-6"></path>
                            </svg>
                        </span>
                        <span class="login-node-label">Monitoring</span>
                    </button>

                </div>

                <!-- penjelasan langkah yang sedang aktif -->
                <div class="login-flow-card" aria-live="polite">
                    <template x-for="(st, i) in flowSteps" :key="i">
                        <div class="login-flow-text" :class="{ 'is-active': flow === i }">
                            <strong x-text="st.title"></strong>
                            <p x-text="st.text"></p>
                        </div>
                    </template>
                </div>

            </div>
        </div>

        <small>KopSync · Sistem POS & Manajemen Pasokan Multi-Cabang</small>

        </div>

        <!-- tombol di layar awal -->
        <button type="button" class="login-enter" id="loginEnterBtn" x-ref="enter" @click="enter()" :disabled="revealing">
            Masuk
        </button>

    </aside>


    <!-- =====================================================
         PANEL KANAN (FORM)
    ====================================================== -->

    <main
        class="login-panel"
        :inert="stage !== 'form'"
        :aria-hidden="(stage !== 'form').toString()"
    >

        <div class="login-stack">

        <!-- CANGKIR: terisi sendiri, klik untuk menuang ke kartu -->
        <button type="button" class="login-cupbtn" id="loginCupBtn" aria-label="Tuang kopi ke kartu login">

            <svg class="login-saucer" viewBox="0 0 64 64" aria-hidden="true">
                <path d="M10 57h44"></path>
            </svg>

            <svg class="login-cup" id="loginCup" viewBox="0 0 64 64" aria-hidden="true">
                <defs>
                    <clipPath id="loginCupClip">
                        <path d="M15 26h32v12a14 14 0 0 1-14 14h-4a14 14 0 0 1-14-14z"></path>
                    </clipPath>
                </defs>

                <g class="login-steam">
                    <path d="M26 20c-3-3 3-5 0-9"></path>
                    <path d="M32 20c-3-3 3-5 0-9"></path>
                    <path d="M38 20c-3-3 3-5 0-9"></path>
                </g>

                <path class="login-cup-bg" d="M15 26h32v12a14 14 0 0 1-14 14h-4a14 14 0 0 1-14-14z"></path>

                <g clip-path="url(#loginCupClip)">
                    <g class="login-coffee-wrap" id="loginCoffeeWrap">
                        <rect class="login-coffee" id="loginCoffee" x="-60" y="26" width="160" height="120"></rect>
                    </g>
                </g>

                <path class="login-cup-line" d="M15 26h32v12a14 14 0 0 1-14 14h-4a14 14 0 0 1-14-14z"></path>
                <path class="login-cup-line" d="M47 29h3a5 5 0 0 1 0 10h-4"></path>
            </svg>
        </button>

        <!-- aliran kopi dari bibir cangkir -->
        <div class="login-stream" id="loginStream" aria-hidden="true"></div>

        <div
            class="login-card @if ($errors->any()) login-card--error @endif"
            :class="{ 'is-shaking': shaking }"
        >

            <!-- tumpahan kopi: mewarnai setengah kartu -->
            <div class="login-spill" aria-hidden="true">
                <div class="login-spill-body" id="loginSpill">
                    <svg class="login-spill-wave" viewBox="0 0 800 14" preserveAspectRatio="none">
                        <path d="M0 0V6c25 0 25 8 50 8s25-8 50-8c25 0 25 8 50 8s25-8 50-8c25 0 25 8 50 8s25-8 50-8c25 0 25 8 50 8s25-8 50-8c25 0 25 8 50 8s25-8 50-8c25 0 25 8 50 8s25-8 50-8c25 0 25 8 50 8s25-8 50-8V0z"></path>
                    </svg>
                </div>
            </div>

            <!-- brand (hanya tampil di HP) -->
            <div class="login-card-brand">
                <div class="logo-box" style="background: var(--brown); color: white;">
                    <img src="{{ asset('image/logo_kopsync.png') }}" alt="Logo KopSync" width="40" height="40">
                </div>

                <div>
                    <strong>KopSync</strong>
                    <span>POS Management</span>
                </div>
            </div>

            <div class="login-head">
                <div>
                    <h2 class="login-title">Masuk ke akun</h2>
                    <p class="login-subtitle">Gunakan akun yang diberikan oleh Admin.</p>
                </div>
            </div>

            <!-- ERROR UMUM (validasi server) -->
            @if ($errors->any())
                <div class="login-alert" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="login-alert" x-show="serverMessage" x-text="serverMessage" x-cloak role="alert"></div>

            <form
                class="login-form"
                method="POST"
                action="{{ route('login.attempt') }}"
                novalidate
                @submit="onSubmit($event)"
                @pageshow.window="stopLoading()"
            >

                @csrf

                <!-- EMAIL / USERNAME -->
                <div class="login-field">

                    <label class="login-label" for="identifier">Email atau Username</label>

                    <div class="login-control">
                        <svg class="login-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"></path>
                        </svg>

                        <input
                            id="identifier"
                            name="identifier"
                            type="text"
                            class="login-input @error('identifier') has-error @enderror"
                            :class="{ 'has-error': clientError.identifier }"
                            placeholder="Masukkan email atau username"
                            autocomplete="username"
                            value="{{ old('identifier') }}"
                            x-ref="identifier"
                            @input="clientError.identifier = ''"
                        >
                    </div>

                    <p class="login-error" x-show="clientError.identifier" x-text="clientError.identifier" x-cloak></p>

                    @error('identifier')
                        <p class="login-error">{{ $message }}</p>
                    @enderror
                </div>

                <!-- PASSWORD -->
                <div class="login-field">

                    <label class="login-label" for="password">Password</label>

                    <div class="login-control login-password">
                        <svg class="login-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="4" y="11" width="16" height="10" rx="2"></rect>
                            <path d="M8 11V8a4 4 0 0 1 8 0v3"></path>
                        </svg>

                        <input
                            id="password"
                            name="password"
                            :type="showPassword ? 'text' : 'password'"
                            class="login-input @error('password') has-error @enderror"
                            :class="{ 'has-error': clientError.password }"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            x-ref="password"
                            @input="clientError.password = ''"
                            @keydown="caps = $event.getModifierState && $event.getModifierState('CapsLock')"
                            @keyup="caps = $event.getModifierState && $event.getModifierState('CapsLock')"
                            @blur="caps = false"
                        >

                        <button
                            type="button"
                            class="login-eye"
                            @click="showPassword = !showPassword"
                            :aria-label="showPassword ? 'Sembunyikan password' : 'Lihat password'"
                        >
                            <svg x-show="!showPassword" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>

                            <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 3l18 18"></path>
                                <path d="M10.6 5.1A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.2M6.5 6.5A17 17 0 0 0 2 12s3.6 7 10 7a10 10 0 0 0 4.2-.9"></path>
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>
                            </svg>
                        </button>
                    </div>

                    <p class="login-note" x-show="caps" x-cloak>Caps Lock sedang aktif.</p>
                    <p class="login-error" x-show="clientError.password" x-text="clientError.password" x-cloak></p>

                    @error('password')
                        <p class="login-error">{{ $message }}</p>
                    @enderror
                </div>

                <!-- OPSI -->
                <div class="login-options">
                    <label class="login-check">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span class="login-switch"></span>
                        Ingat saya
                    </label>

                    <span>Lupa password? Hubungi Admin</span>
                </div>

                <!-- SUBMIT -->
                <button type="submit" class="login-submit" :disabled="loading">
                    <span x-show="!loading">Masuk</span>

                    <span class="login-busy" x-show="loading" x-cloak>
                        <i class="login-spinner" aria-hidden="true"></i>
                        Memproses...
                    </span>
                </button>

            </form>

            <p class="login-footer">
                Belum punya akun? <strong>Hubungi Admin</strong>
            </p>

        </div>

        </div>

    </main>


    <!-- COFFEE LOADING: hanya tampil ketika form login dikirim.
         Kopi dituang tepat di ujung bar. Bar hanya bertambah saat kopi mengalir. -->
    <div
        class="login-loading"
        x-show="loading"
        x-cloak
        role="status"
        aria-live="polite"
    >
        <div
            class="login-loading-content"
            :class="{ 'is-pouring': pouring, 'is-flowing': flowing }"
        >
            <div class="login-loading-cup-area" :style="'--p:' + loadingProgress">

                <svg class="login-loading-cup" viewBox="0 0 64 64" aria-hidden="true">
                    <defs>
                        <clipPath id="loadingCupClip">
                            <path d="M15 26h32v12a14 14 0 0 1-14 14h-4a14 14 0 0 1-14-14z"></path>
                        </clipPath>
                    </defs>

                    <g class="login-loading-steam">
                        <path d="M26 20c-3-3 3-5 0-9"></path>
                        <path d="M32 20c-3-3 3-5 0-9"></path>
                        <path d="M38 20c-3-3 3-5 0-9"></path>
                    </g>

                    <path class="login-loading-cup-bg" d="M15 26h32v12a14 14 0 0 1-14 14h-4a14 14 0 0 1-14-14z"></path>

                    <g clip-path="url(#loadingCupClip)">
                        <g class="login-loading-wrap">
                            <rect class="login-loading-coffee" x="-60" y="26" width="160" height="120"></rect>
                        </g>
                    </g>

                    <path class="login-loading-cup-line" d="M15 26h32v12a14 14 0 0 1-14 14h-4a14 14 0 0 1-14-14z"></path>
                    <path class="login-loading-cup-line" d="M47 29h3a5 5 0 0 1 0 10h-4"></path>
                </svg>

                <div class="login-loading-stream" aria-hidden="true"></div>
            </div>

            <div
                class="login-loading-track"
                role="progressbar"
                aria-label="Proses login"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-valuenow="Math.round(loadingProgress)"
            >
                <div
                    class="login-loading-fill"
                    :style="'width: ' + loadingProgress + '%'"
                ></div>
            </div>

            <strong class="login-loading-percent" x-text="Math.round(loadingProgress) + '%'">0%</strong>
            <p class="login-loading-message" x-text="loadingMessage">Menghubungkan ke KopSync...</p>
        </div>
    </div>

</div>

<script>
    document.addEventListener('alpine:init', () => {

        Alpine.data('loginPage', (startForm = false) => ({

            /* ---------- tahap tampilan: intro -> form ---------- */
            stage: startForm ? 'form' : 'intro',
            revealing: false,

            /* ---------- form ---------- */
            showPassword: false,
            loading: false,
            serverMessage: '',
            caps: false,
            shaking: false,

            clientError: { identifier: '', password: '' },

            /* ---------- coffee loading ---------- */
            loadingProgress: 0,
            loadingMessage: 'Menghubungkan ke KopSync...',
            pouring: false,      // cangkir sedang miring
            flowing: false,      // kopi benar-benar mengalir (hanya saat ini bar bertambah)
            finishing: false,    // dashboard sudah siap, tinggal tuang sisa sampai 100%
            cap: 90,             // batas bar selama server masih bekerja
            loadToken: 0,        // untuk membatalkan loop lama
            loopPromise: null,

            /* ---------- sapaan + jam ---------- */
            greeting: '',
            dateText: '',
            timeText: '',

            /* ---------- alur operasional panel kiri ---------- */
            flow: 0,
            paused: false,

            flowSteps: [
                { title: 'Distribusi dari DC',  text: 'Barang dari DC dikirim ke cabang dan statusnya bisa dipantau.' },
                { title: 'Stok cabang',         text: 'Barang yang diterima menambah stok cabang.' },
                { title: 'Kasir',               text: 'Penjualan dicatat di kasir dan ikut terhitung di stok.' },
                { title: 'Pencatatan waste',    text: 'Barang rusak atau terbuang dicatat supaya stok tetap akurat.' },
                { title: 'Monitoring',          text: 'Semua pergerakan terangkum untuk dipantau.' },
            ],

            start() {
                this.tick();
                setInterval(() => this.tick(), 30000);

                if (this.stage === 'form') {
                    // dibuka langsung di form (mis. setelah login gagal)
                    this.focusFirst(900);
                } else {
                    // fokus ke tombol agar bisa langsung ditekan dengan Enter
                    setTimeout(() => this.$refs.enter && this.$refs.enter.focus(), 900);
                }

                this.startFlow();
            },

            /* ---------- layar awal -> form ---------- */
            async enter() {
                if (this.stage === 'form' || this.revealing) return;

                this.revealing = true;

                try {
                    // animasi geser disediakan oleh script Motion di bawah
                    const api = window.kopsyncLogin;

                    if (api && api.reveal) await api.reveal();
                } catch (err) {
                    // kalau animasi gagal, form langsung ditampilkan
                }

                this.stage = 'form';

                // buang gaya sementara dari animasi; tampilan akhir diatur CSS (.is-form)
                this.$nextTick(() => {
                    if (this.$refs.brand) this.$refs.brand.style.clipPath = '';
                });

                this.focusFirst(60);
            },

            // fokus ke input pertama yang masih kosong
            focusFirst(ms = 0) {
                setTimeout(() => {
                    const id = this.$refs.identifier;
                    const pw = this.$refs.password;

                    if (id && !id.value) id.focus();
                    else if (pw) pw.focus();
                }, ms);
            },

            tick() {
                const now = new Date();
                const h = now.getHours();

                this.greeting =
                    h < 11 ? 'Selamat pagi' :
                    h < 15 ? 'Selamat siang' :
                    h < 18 ? 'Selamat sore' : 'Selamat malam';

                this.dateText = now.toLocaleDateString('id-ID', {
                    weekday: 'long', day: 'numeric', month: 'long',
                });

                this.timeText = now.toLocaleTimeString('id-ID', {
                    hour: '2-digit', minute: '2-digit',
                });
            },

            /* ---------- alur operasional ---------- */
            startFlow() {
                const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                if (reduce) return;

                // pindah ke langkah berikutnya, berhenti saat kursor / fokus ada di alur
                setInterval(() => {
                    if (!this.paused) this.flow = (this.flow + 1) % this.flowSteps.length;
                }, 3200);
            },

            selectFlow(i) {
                this.flow = i;
            },

            /* ---------- gerak kursor ---------- */
            onMoveLeft(e) {
                const el = e.currentTarget;

                // layar awal: seluruh layar. Setelah form tampil: hanya sisi kiri yang terlihat
                const area = this.stage === 'form' ? el.querySelector('.login-brand-inner') : el;
                const r = area.getBoundingClientRect();

                const clamp = (n) => Math.max(-1, Math.min(1, n));

                el.style.setProperty('--px', clamp(((e.clientX - r.left) / r.width - 0.5) * 2).toFixed(3));
                el.style.setProperty('--py', clamp(((e.clientY - r.top) / r.height - 0.5) * 2).toFixed(3));
            },

            onLeaveLeft(e) {
                e.currentTarget.style.setProperty('--px', '0');
                e.currentTarget.style.setProperty('--py', '0');
            },

            /* ---------- kirim form ---------- */
            shake() {
                this.shaking = false;
                requestAnimationFrame(() => requestAnimationFrame(() => (this.shaking = true)));
                setTimeout(() => (this.shaking = false), 600);
            },

            /* =====================================================
               COFFEE LOADING
               Urutan satu siklus menuang:
               1. cangkir miring (0.5 detik)
               2. kopi mengalir  -> bar bertambah
               3. aliran berhenti, cangkir tegak, terisi lagi -> bar DIAM
               Selama menunggu respons server (mentok 90%), cangkir tegak dan bar diam.
            ===================================================== */

            sleep(ms) {
                return new Promise(resolve => setTimeout(resolve, ms));
            },

            loadingAborted(token) {
                return token !== this.loadToken || !this.loading;
            },

            updateLoadingMessage() {
                if (this.loadingProgress < 30) {
                    this.loadingMessage = 'Memeriksa akun...';
                } else if (this.loadingProgress < 60) {
                    this.loadingMessage = 'Memverifikasi keamanan...';
                } else {
                    this.loadingMessage = 'Menyiapkan sistem KopSync...';
                }
            },

            startLoading() {
                this.loadToken++;

                this.loading = true;
                this.finishing = false;
                this.cap = 90;
                this.pouring = false;
                this.flowing = false;
                this.loadingProgress = 0;
                this.loadingMessage = 'Memeriksa akun...';

                this.loopPromise = this.runLoading(this.loadToken);
            },

            async runLoading(token) {
                // beri jeda sebentar supaya overlay terlihat dulu dengan cangkir tegak
                await this.sleep(350);

                while (!this.loadingAborted(token) && !this.finishing) {

                    // sudah di batas: tunggu server, cangkir tegak, bar diam
                    if (this.loadingProgress >= this.cap) {
                        this.loadingMessage = 'Menyiapkan dashboard...';
                        await this.sleep(150);
                        continue;
                    }

                    // 1) cangkir miring dulu
                    this.pouring = true;
                    await this.sleep(520);

                    if (this.loadingAborted(token) || this.finishing) break;

                    // 2) kopi mengalir: hanya di sini bar bertambah
                    this.flowing = true;

                    const until = Date.now() + 1100;

                    while (
                        Date.now() < until &&
                        !this.loadingAborted(token) &&
                        !this.finishing &&
                        this.loadingProgress < this.cap
                    ) {
                        this.loadingProgress = Math.min(this.cap, this.loadingProgress + 1.2);
                        this.updateLoadingMessage();
                        await this.sleep(100);
                    }

                    // 3) aliran berhenti, cangkir tegak: bar ikut berhenti
                    this.flowing = false;
                    this.pouring = false;

                    if (this.loadingAborted(token) || this.finishing) break;

                    await this.sleep(950); // cangkir terisi lagi
                }

                this.flowing = false;
                this.pouring = false;
            },

            // pasang HTML dashboard yang SUDAH diunduh, tanpa request kedua.
            // Dipanggil tepat saat bar 100%, jadi langsung masuk tanpa jeda.
            enterDashboard(html, url) {
                try {
                    // hentikan timer halaman login (jam, alur operasional, dll.)
                    let id = setTimeout(() => {}, 0);
                    while (id--) { clearTimeout(id); clearInterval(id); }

                    // lepas Alpine dari halaman login supaya tidak bentrok dengan Alpine dashboard
                    if (window.Alpine && window.Alpine.destroyTree) {
                        window.Alpine.destroyTree(document.body);
                    }

                    // alamat diganti dulu agar URL relatif di dashboard benar
                    history.replaceState(null, '', url);

                    document.open();
                    document.write(html);
                    document.close();
                } catch (err) {
                    // kalau pemasangan gagal, pakai cara biasa
                    window.location.assign(url);
                }
            },

            // dashboard sudah siap: tuang sisa kopi sampai 100%, lalu LANGSUNG masuk
            async finishLoading() {
                const token = this.loadToken;

                this.finishing = true;

                if (this.loopPromise) {
                    try { await this.loopPromise; } catch (e) { /* abaikan */ }
                }

                if (this.loadingAborted(token)) return;

                this.loadingMessage = 'Berhasil! Membuka dashboard...';

                if (!this.pouring) {
                    this.pouring = true;
                    await this.sleep(450);
                }

                this.flowing = true;

                while (this.loadingProgress < 100 && !this.loadingAborted(token)) {
                    this.loadingProgress = Math.min(100, this.loadingProgress + 4);
                    await this.sleep(50);
                }

                // 100% tercapai: tidak ada jeda lagi
            },

            // login gagal / halaman dibuka lagi: matikan animasi
            stopLoading() {
                this.loadToken++;

                this.loading = false;
                this.finishing = false;
                this.pouring = false;
                this.flowing = false;
                this.loadingProgress = 0;
            },

            async onSubmit(e) {
                e.preventDefault();

                if (this.loading) return;

                const form = e.currentTarget;
                const id = this.$refs.identifier;
                const pw = this.$refs.password;

                this.clientError.identifier = id.value.trim() ? '' : 'Isi email atau username.';
                this.clientError.password = pw.value ? '' : 'Isi password.';

                if (this.clientError.identifier || this.clientError.password) {
                    this.shake();
                    (this.clientError.identifier ? id : pw).focus();
                    return;
                }

                this.serverMessage = '';
                this.startLoading();

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    // Laravel mengembalikan redirect menuju halaman sesuai role.
                    if (response.ok && response.redirected) {
                        const type = response.headers.get('content-type') || '';
                        const url  = response.url;

                        // server sudah selesai (login + dashboard). Tuang sisa kopi sampai 100%.
                        const html = type.includes('text/html') ? await response.text() : null;

                        await this.finishLoading();

                        // 100% -> jeda 0,9 detik, lalu masuk
                        await this.sleep(900);

                        if (html) this.enterDashboard(html, url);
                        else window.location.assign(url);

                        return;
                    }

                    // Validasi gagal: tampilkan pesan Laravel tanpa reload halaman.
                    if (response.status === 422) {
                        const data = await response.json();
                        const errors = data.errors || {};

                        this.serverMessage =
                            errors.identifier?.[0] ||
                            errors.password?.[0] ||
                            data.message ||
                            'Login gagal. Periksa kembali akun dan password.';

                        this.stopLoading();
                        this.shake();
                        return;
                    }

                    // Respons lain, misalnya sesi kedaluwarsa atau server bermasalah.
                    let data = {};

                    try {
                        data = await response.json();
                    } catch (error) {
                        // Respons server bukan JSON.
                    }

                    throw new Error(
                        data.message || 'Login belum berhasil. Silakan coba lagi.'
                    );

                } catch (error) {
                    this.stopLoading();
                    this.serverMessage = error.message || 'Tidak dapat terhubung ke server.';
                    this.shake();
                }
            },

        }));

    });
</script>

<script type="module">

    // Motion dimuat dari CDN. Kalau gagal dimuat, cangkir tetap tampil penuh.
    const coffee   = document.getElementById('loginCoffee');
    const wrap     = document.getElementById('loginCoffeeWrap');
    const cupSvg   = document.getElementById('loginCup');
    const cupBtn   = document.getElementById('loginCupBtn');
    const stream   = document.getElementById('loginStream');
    const spill    = document.getElementById('loginSpill');
    const card     = document.querySelector('.login-card');
    const submit   = card ? card.querySelector('.login-submit') : null;
    const page     = document.querySelector('.login-page');
    const enterBtn = document.getElementById('loginEnterBtn');

    let motion = null;

    try {
        motion = await import('https\://cdn.jsdelivr.net/npm/motion\@14.11.1/+esm');
    } catch (err) {
        if (coffee) coffee.style.transform = 'translateY(0)';
        if (cupSvg) cupSvg.classList.add('is-steaming');
    }

    if (motion && coffee && cupBtn) {

        const { animate, hover, press } = motion;

        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const D = (s) => (reduce ? 0 : s);

        const soft = { type: 'spring', stiffness: 260, damping: 22 };
        const ease = [0.4, 0, 0.2, 1];

        const EMPTY = 28;      // posisi kopi saat cangkir kosong
        const POUR_MS = 2.4;   // lama kopi mengalir

        // batas potong tumpahan (bawahnya dilonggarkan agar tepi bergelombang tidak terpotong)
        const SPILL_FROM = 'inset(0px 0px -20px 78%)';
        const SPILL_TO   = 'inset(0px 0px -20px 0%)';

        let state = 'idle';    // idle | spilled
        let busy = true;       // terkunci selama animasi berjalan

        /* ---------- cangkir terisi sendiri ---------- */
        async function fill(delay = 0) {
            busy = true;

            await animate(coffee, { y: [EMPTY, 0] }, {
                duration: D(1.8),
                delay: D(delay),
                ease: [0.22, 1, 0.36, 1],
            });

            cupSvg.classList.add('is-steaming');
            busy = false;
        }

        /* ---------- klik: cangkir miring, kopi tumpah ke kartu ---------- */
        async function pour() {
            busy = true;

            cupSvg.classList.remove('is-steaming');

            // cangkir miring, permukaan kopi tetap datar
            const tilt = { duration: D(0.8), ease };

            await Promise.all([
                animate(cupSvg, { rotate: -78 }, tilt),
                animate(wrap,   { rotate: 78 },  tilt),
            ]);

            // aliran kopi dari bibir cangkir
            stream.style.transformOrigin = '50% 0';
            await animate(stream, { scaleY: [0, 1] }, { duration: D(0.3), ease: 'easeIn' });

            // kopi di cangkir berkurang, kartu terwarnai dari atas sampai setengahnya
            const half = card.offsetHeight * 0.5;

            animate(coffee, { y: EMPTY }, { duration: D(POUR_MS), ease: 'linear' });
            animate(spill, { opacity: [0.7, 0.26] }, { duration: D(POUR_MS + 0.8), ease: 'easeOut' });

            // tumpahan mulai dari sisi kanan (tempat kopi jatuh), lalu melebar ke kiri sambil turun
            animate(spill, { clipPath: [SPILL_FROM, SPILL_TO] }, {
                duration: D(POUR_MS * 0.8),
                ease: [0.25, 0.6, 0.35, 1],
            });

            await animate(spill, { y: [0, half] }, {
                duration: D(POUR_MS),
                ease: [0.3, 0.5, 0.3, 1],
            });

            // aliran berhenti, cangkir kembali tegak
            stream.style.transformOrigin = '50% 100%';
            animate(stream, { scaleY: 0 }, { duration: D(0.35) });

            await Promise.all([
                animate(cupSvg, { rotate: 0 }, tilt),
                animate(wrap,   { rotate: 0 }, tilt),
            ]);

            state = 'spilled';
            cupBtn.setAttribute('aria-label', 'Bersihkan tumpahan kopi dan isi ulang cangkir');
            busy = false;
        }

        /* ---------- klik lagi: tumpahan hilang, cangkir terisi ulang ---------- */
        async function clean() {
            busy = true;

            await animate(spill, { opacity: 0 }, { duration: D(0.7) });
            await animate(spill, { y: 0, clipPath: SPILL_FROM }, { duration: 0 });

            state = 'idle';
            cupBtn.setAttribute('aria-label', 'Tuang kopi ke kartu login');

            await fill();
        }

        cupBtn.addEventListener('click', () => {
            if (busy) return;

            if (state === 'idle') pour();
            else clean();
        });

        // cangkir bergoyang sedikit saat kursor di atasnya (petunjuk bisa diklik)
        if (!reduce) {
            hover(cupBtn, () => {
                if (busy || state !== 'idle') return;

                animate(cupSvg, { rotate: -7 }, soft);

                return () => {
                    if (!busy) animate(cupSvg, { rotate: 0 }, soft);
                };
            });

            press(cupBtn, () => {
                animate(cupBtn, { scale: 0.94 }, { duration: 0.12 });

                return () => animate(cupBtn, { scale: 1 }, soft);
            });
        }

        /* ---------- kartu naik saat di-hover (cangkir ikut naik) ---------- */
        if (!reduce && card) {
            const shadowRest  = '0 5px 20px rgba(86, 54, 25, 0.06)';
            const shadowHover = '0 18px 40px rgba(86, 54, 25, 0.14)';

            hover(card, () => {
                animate(card,   { y: -5, scale: 1.008, boxShadow: shadowHover }, soft);
                animate(cupBtn, { y: -5 }, soft);

                return () => {
                    animate(card,   { y: 0, scale: 1, boxShadow: shadowRest }, soft);
                    animate(cupBtn, { y: 0 }, soft);
                };
            });
        }

        /* ---------- tombol Masuk ---------- */
        if (!reduce && submit) {
            hover(submit, () => {
                if (submit.disabled) return;

                animate(submit, { y: -1 }, soft);

                return () => animate(submit, { y: 0 }, soft);
            });

            press(submit, () => {
                if (submit.disabled) return;

                animate(submit, { scale: 0.97 }, { duration: 0.12 });

                return () => animate(submit, { scale: 1 }, soft);
            });
        }

        /* ---------- tombol di layar awal ---------- */
        if (!reduce && enterBtn) {
            hover(enterBtn, () => {
                if (enterBtn.disabled) return;

                animate(enterBtn, { y: -2 }, soft);

                return () => animate(enterBtn, { y: 0 }, soft);
            });

            press(enterBtn, () => {
                if (enterBtn.disabled) return;

                animate(enterBtn, { scale: 0.96 }, { duration: 0.12 });

                return () => animate(enterBtn, { scale: 1 }, soft);
            });
        }

        /* ---------- layar awal: panel coklat bergeser ke kiri, form masuk dari kanan ---------- */
        async function reveal() {
            const brand = document.querySelector('.login-brand');
            const inner = brand.querySelector('.login-brand-inner');
            const body  = brand.querySelector('.login-brand-body');
            const stack = document.querySelector('.login-stack');

            const vars = getComputedStyle(page);
            const cr   = parseFloat(vars.getPropertyValue('--cr')) || 0;    // lebar panel coklat di akhir
            const pad  = parseFloat(vars.getPropertyValue('--pad')) || 0;

            const rightInset = ((1 - cr) * 100).toFixed(3) + '%';

            // seberapa jauh isi (headline + alur) bergeser dari tengah layar ke posisi akhirnya di kiri
            const shift = body.getBoundingClientRect().left - (inner.getBoundingClientRect().left + pad);

            const move = { duration: D(1), ease: [0.76, 0, 0.24, 1] };

            animate(enterBtn, { opacity: 0, y: 14 }, { duration: D(0.3) });
            animate(body, { x: [shift, 0] }, move);

            animate(stack, { opacity: [0, 1], x: [70, 0] }, {
                duration: D(0.8),
                delay: D(0.35),
                ease: [0.22, 1, 0.36, 1],
            });

            await animate(brand, {
                clipPath: ['inset(0px 0% 0px 0px)', `inset(0px ${rightInset} 0px 0px)`],
            }, move);

            // cangkir mulai terisi setelah form terlihat
            fill(0.15);
        }

        window.kopsyncLogin = { reveal };

        // dibuka langsung di form: isi cangkir sekarang. Kalau tidak, tunggu layar awal dibuka.
        if (page && page.classList.contains('is-form')) fill(0.6);
    }

</script>

@endsection