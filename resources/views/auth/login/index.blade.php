@extends('layouts.auth')

@section('title', 'Login')

@section('content')

<div class="login-page" x-data="loginPage()" x-init="start()">

    <!-- =====================================================
         PANEL KIRI  (pratinjau sistem yang bergerak)
    ====================================================== -->

    <aside class="login-brand" @mousemove="onMoveLeft($event)" @mouseleave="onLeaveLeft($event)">

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

    </aside>


    <!-- =====================================================
         PANEL KANAN (FORM)
    ====================================================== -->

    <main class="login-panel">

        <!-- latar: titik halus + bercak warna yang bergerak pelan -->
        <div class="login-dots" aria-hidden="true"></div>

        <div class="login-blobs" aria-hidden="true">
            <i class="login-blob login-blob--a"></i>
            <i class="login-blob login-blob--b"></i>
        </div>

        <div class="login-clock" x-cloak>
            <strong x-text="greeting"></strong>
            <span x-text="dateText + ' · ' + timeText"></span>
        </div>

        <div
            class="login-card @if ($errors->any()) login-card--error @endif"
            :class="{ 'is-shaking': shaking, 'is-full': progress >= 1 }"
            :style="{ '--p': progress }"
        >

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

                <!-- cangkir terisi seiring form diisi -->
                <svg class="login-cup" viewBox="0 0 64 64" width="52" height="52" aria-hidden="true">
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
                        <rect class="login-coffee" x="12" y="26" width="38" height="28"></rect>
                    </g>

                    <path class="login-cup-line" d="M15 26h32v12a14 14 0 0 1-14 14h-4a14 14 0 0 1-14-14z"></path>
                    <path class="login-cup-line" d="M47 29h3a5 5 0 0 1 0 10h-4"></path>
                    <path class="login-cup-line" d="M10 57h44"></path>
                </svg>
            </div>

            <!-- ERROR UMUM (validasi server) -->
            @if ($errors->any())
                <div class="login-alert" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                class="login-form"
                method="POST"
                action="{{ route('login.attempt') }}"
                novalidate
                @submit="onSubmit($event)"
                @pageshow.window="loading = false"
            >

                @csrf

                <!-- EMAIL / USERNAME -->
                <div class="login-field">

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
                            placeholder=" "
                            autocomplete="username"
                            value="{{ old('identifier') }}"
                            x-ref="identifier"
                            @input="clientError.identifier = ''; syncFilled()"
                            @change="syncFilled()"
                        >

                        <label class="login-float" for="identifier">Email atau Username</label>
                    </div>

                    <p class="login-error" x-show="clientError.identifier" x-text="clientError.identifier" x-cloak></p>

                    @error('identifier')
                        <p class="login-error">{{ $message }}</p>
                    @enderror
                </div>

                <!-- PASSWORD -->
                <div class="login-field">

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
                            placeholder=" "
                            autocomplete="current-password"
                            x-ref="password"
                            @input="clientError.password = ''; syncFilled()"
                            @change="syncFilled()"
                            @keydown="caps = $event.getModifierState && $event.getModifierState('CapsLock')"
                            @keyup="caps = $event.getModifierState && $event.getModifierState('CapsLock')"
                            @blur="caps = false"
                        >

                        <label class="login-float" for="password">Password</label>

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

    </main>

</div>

<script>
    document.addEventListener('alpine:init', () => {

        Alpine.data('loginPage', () => ({

            /* ---------- form ---------- */
            showPassword: false,
            loading: false,
            caps: false,
            shaking: false,

            clientError: { identifier: '', password: '' },
            filled: { identifier: false, password: false },

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

            /* cangkir terisi: 0 - 1 */
            get progress() {
                if (this.loading) return 1;

                return (this.filled.identifier ? 0.5 : 0) + (this.filled.password ? 0.5 : 0);
            },

            start() {
                this.tick();
                setInterval(() => this.tick(), 30000);

                // isian otomatis browser tidak selalu memicu event input
                [0, 300, 1000].forEach((ms) => setTimeout(() => this.syncFilled(), ms));

                // fokus ke input pertama yang masih kosong
                setTimeout(() => {
                    const id = this.$refs.identifier;
                    const pw = this.$refs.password;

                    if (id && !id.value) id.focus();
                    else if (pw) pw.focus();
                }, 700);

                this.startFlow();
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

            syncFilled() {
                const id = this.$refs.identifier;
                const pw = this.$refs.password;

                this.filled.identifier = !!(id && id.value.trim());
                this.filled.password   = !!(pw && pw.value);
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
                const r = el.getBoundingClientRect();
                const x = (e.clientX - r.left) / r.width;
                const y = (e.clientY - r.top) / r.height;

                el.style.setProperty('--px', ((x - 0.5) * 2).toFixed(3));
                el.style.setProperty('--py', ((y - 0.5) * 2).toFixed(3));
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

            onSubmit(e) {
                const id = this.$refs.identifier;
                const pw = this.$refs.password;

                this.clientError.identifier = id.value.trim() ? '' : 'Isi email atau username.';
                this.clientError.password   = pw.value ? '' : 'Isi password.';

                if (this.clientError.identifier || this.clientError.password) {
                    e.preventDefault();
                    this.shake();
                    (this.clientError.identifier ? id : pw).focus();
                    return;
                }

                this.loading = true;
            },

        }));

    });
</script>

<script type="module">
    import { animate, hover, press } from 'https://cdn.jsdelivr.net/npm/motion@14.11.1/+esm';

    // lewati animasi kalau pengguna mematikan gerakan di perangkatnya
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {

        const card   = document.querySelector('.login-card');
        const cup    = card ? card.querySelector('.login-cup') : null;
        const submit = card ? card.querySelector('.login-submit') : null;

        const soft = { type: 'spring', stiffness: 260, damping: 22 };

        const shadowRest  = '0 5px 20px rgba(86, 54, 25, 0.06)';
        const shadowHover = '0 18px 40px rgba(86, 54, 25, 0.14)';

        // kartu terangkat saat di-hover, cangkir ikut miring sedikit
        if (card) {
            hover(card, () => {
                animate(card, { y: -5, scale: 1.008, boxShadow: shadowHover }, soft);
                if (cup) animate(cup, { rotate: -8 }, soft);

                return () => {
                    animate(card, { y: 0, scale: 1, boxShadow: shadowRest }, soft);
                    if (cup) animate(cup, { rotate: 0 }, soft);
                };
            });
        }

        // tombol Masuk: naik tipis saat hover, menekan saat diklik
        if (submit) {
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
    }
</script>

@endsection