@extends('layouts.app')

@section('title', 'Role & Hak Akses')
@section('description', 'Atur fitur yang boleh diakses setiap role.')

@php
    // DEMO: fitur dan hak akses awal dibaca dari config/menu.php (menu sidebar tiap role),
    // aturan terkunci dari config/demo_admin.php. Nanti diganti data dari database.
    $roles = collect(config('menu.roles'))
        ->map(fn ($meta, $key) => ['key' => $key, 'label' => $meta['label']])
        ->values()
        ->all();

    $features = [];   // satu baris per route menu
    $access   = [];   // role => daftar route yang dimiliki

    foreach (config('menu.menus') as $role => $sections) {
        foreach ($sections as $items) {
            foreach ($items as $item) {

                $route = $item['route'];

                if (! isset($features[$route])) {
                    $features[$route] = [
                        'route' => $route,
                        'label' => $item['label'],
                        'group' => explode('.', $route)[0],   // kasir | ic | manager | dc | admin
                    ];
                }

                $access[$role][] = $route;
            }
        }
    }

    $features = array_values($features);
    $locks    = config('demo_admin.locks');
@endphp

@section('content')

<section class="page active" x-data="rolePage()" @keydown.escape.window="confirming = false">

    <!-- PESAN BERHASIL -->
    <div class="flash-success" x-show="flash" x-text="flash" x-cloak role="status"></div>

    <div class="page-card">

        <div class="table-wrapper">
            <table class="matrix">

                <thead>
                    <tr>
                        <th>Fitur</th>
                        <template x-for="role in roles" :key="role.key">
                            <th>
                                <span x-text="role.label"></span>
                                <span class="cell-sub" x-text="countOf(role.key) + ' fitur'"></span>
                            </th>
                        </template>
                    </tr>
                </thead>

                <!-- satu <tbody> per kelompok fitur -->
                <template x-for="group in groups" :key="group.key">
                    <tbody>

                        <tr class="group-row">
                            <td colspan="6" x-text="group.label"></td>
                        </tr>

                        <template x-for="feature in group.features" :key="feature.route">
                            <tr>
                                <td x-text="feature.label"></td>

                                <template x-for="role in roles" :key="role.key">
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="check-input"
                                            :checked="grants[role.key][feature.route]"
                                            :disabled="!!lockOf(role.key, feature.route)"
                                            :title="lockOf(role.key, feature.route) ? lockOf(role.key, feature.route).reason : ''"
                                            :aria-label="role.label + ': ' + feature.label"
                                            @change="toggle(role.key, feature.route, $event.target.checked)"
                                        >

                                        <span
                                            class="lock-mark"
                                            x-show="lockOf(role.key, feature.route)"
                                            :title="lockOf(role.key, feature.route) ? lockOf(role.key, feature.route).reason : ''"
                                        ></span>
                                    </td>
                                </template>
                            </tr>
                        </template>

                    </tbody>
                </template>

            </table>
        </div>


        <div class="receive-footer">

            <p class="info-note" x-text="dirty
                ? changes.length + ' perubahan belum disimpan.'
                : 'terkunci mengikuti aturan sistem. Menu sidebar tiap role mengikuti hak akses ini.'"></p>

            <div class="receive-actions">
                <button type="button" class="outline-button btn-sm" :disabled="!dirty" @click="resetChanges()">Reset Perubahan</button>
                <button type="button" class="primary-button" :disabled="!dirty" @click="confirming = true">Simpan Perubahan</button>
            </div>

        </div>

    </div>


    <!-- =====================================================
         DIALOG KONFIRMASI
    ====================================================== -->

    <div class="modal-backdrop" x-show="confirming" x-cloak @click.self="confirming = false">

        <div class="modal-box wide" role="dialog" aria-modal="true" aria-labelledby="role-confirm-title">

            <h4 id="role-confirm-title">Simpan Perubahan Hak Akses</h4>

            <p>Menu sidebar tiap role akan mengikuti hak akses yang baru. Lanjutkan?</p>

            <div class="summary-box">

                <p class="summary-title" x-text="changes.length + ' perubahan'"></p>

                <template x-for="change in changes" :key="change.role + change.route">
                    <div class="summary-line">
                        <span x-text="roleLabel(change.role)"></span>
                        <span x-text="(change.now ? 'Diberi akses: ' : 'Dicabut: ') + featureLabel(change.route)"></span>
                    </div>
                </template>

            </div>

            <div class="modal-actions">
                <button type="button" class="outline-button btn-sm" @click="confirming = false">Batal</button>
                <button type="button" class="primary-button btn-sm" @click="save()">Ya, Simpan</button>
            </div>

        </div>

    </div>

</section>

@endsection

@push('scripts')
<script>
    // DEMO: perubahan hanya di tampilan. Nanti disimpan ke database oleh backend,
    // dan menu sidebar dibuat dari hak akses yang tersimpan.
    function rolePage() {

        const roles    = @json($roles);
        const features = @json($features);
        const access   = @json($access);
        const locks    = @json($locks);

        // Hak akses awal: dari menu tiap role, lalu dipaksa mengikuti aturan terkunci
        const lockOf = (role, route) => {

            if (route.startsWith(locks.admin_only_prefix) && role !== 'admin') {
                return { value: false, reason: 'Fitur sistem hanya untuk Admin.' };
            }

            if ((locks.always_on[role] || []).includes(route)) {
                return { value: true, reason: 'Admin harus selalu bisa mengelola pengguna dan role.' };
            }

            if ((locks.blocked[role] || []).includes(route)) {
                return { value: false, reason: 'Role ini tidak boleh melihat omzet dan laba rugi.' };
            }

            return null;
        };

        const initial = {};

        roles.forEach(role => {
            initial[role.key] = {};

            features.forEach(feature => {

                const lock = lockOf(role.key, feature.route);

                initial[role.key][feature.route] = lock
                    ? lock.value
                    : (access[role.key] || []).includes(feature.route);
            });
        });

        const clone = (value) => JSON.parse(JSON.stringify(value));

        return {

            roles,
            features,

            grants: clone(initial),       // kondisi yang sedang diedit
            saved: clone(initial),        // kondisi terakhir disimpan

            confirming: false,
            flash: '',


            /* ---------- TAMPILAN ---------- */

            // Fitur dikelompokkan per role asalnya (urutan sama dengan daftar role)
            get groups() {

                return this.roles
                    .map(role => ({
                        key: role.key,
                        label: role.label,
                        features: this.features.filter(feature => feature.group === role.key),
                    }))
                    .filter(group => group.features.length > 0);
            },


            roleLabel(key) {

                const found = this.roles.find(role => role.key === key);

                return found ? found.label : key;
            },


            featureLabel(route) {

                const found = this.features.find(feature => feature.route === route);

                return found ? found.label : route;
            },


            countOf(role) {

                return Object.values(this.grants[role]).filter(Boolean).length;
            },


            lockOf,


            /* ---------- PERUBAHAN ---------- */

            toggle(role, route, checked) {

                if (lockOf(role, route)) {
                    return;
                }

                this.grants[role][route] = checked;
            },


            // Selisih antara kondisi yang diedit dan yang terakhir disimpan
            get changes() {

                const list = [];

                this.roles.forEach(role => {
                    this.features.forEach(feature => {

                        const now = this.grants[role.key][feature.route];

                        if (now !== this.saved[role.key][feature.route]) {
                            list.push({ role: role.key, route: feature.route, now });
                        }
                    });
                });

                return list;
            },


            get dirty() {

                return this.changes.length > 0;
            },


            resetChanges() {

                this.grants = clone(this.saved);
            },


            save() {

                const total = this.changes.length;

                this.saved = clone(this.grants);
                this.confirming = false;

                this.flash = total + ' perubahan hak akses berhasil disimpan.';

                setTimeout(() => { this.flash = ''; }, 5000);

                window.scrollTo({ top: 0 });
            },

        };
    }
</script>
@endpush