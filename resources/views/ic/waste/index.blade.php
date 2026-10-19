@extends('layouts.app')

@section('title', 'Waste Log')
@section('description', 'Catat barang rusak atau terbuang beserta alasannya.')

@php
    // Data diisi dari backend via GET /ic/waste/items dan /ic/waste/menus (diload via JS).
    $user = auth()->user();
    $by = $user->nama_lengkap;
    $items = [];
    $menus = [];

    // DEMO: riwayat waste contoh (nanti diganti data dari database).
    $history = [];
@endphp

@section('content')

<section
    class="page active"
    x-data="wastePage()" x-init="init()"
    @keydown.escape.window="closeTop()"
>

    <!-- PESAN BERHASIL -->
    <div class="flash-success" x-show="flash" x-text="flash" x-cloak role="status"></div>


    <!-- =====================================================
         RIWAYAT WASTE
    ====================================================== -->

    <div class="page-card">

        <div class="page-toolbar">
            <button type="button" class="primary-button" @click="openForm()">+ Catat Waste</button>
        </div>

        <div class="table-toolbar">

            <input
                type="search"
                class="search-input"
                placeholder="Cari nama barang atau menu..."
                aria-label="Cari nama barang atau menu"
                x-model="search"
            >

            <select class="select" aria-label="Filter jenis" x-model="filterType">
                <option value="">Semua Jenis</option>
                <option value="bahan">Bahan / Barang</option>
                <option value="menu">Menu</option>
            </select>

            <select class="select" aria-label="Filter alasan" x-model="filterReason">
                <option value="">Semua Alasan</option>
                <template x-for="reason in allReasons" :key="reason">
                    <option :value="reason" x-text="reason"></option>
                </template>
            </select>

            <span class="table-meta" x-text="'Menampilkan ' + filtered.length + ' dari ' + history.length + ' catatan'"></span>

        </div>

        <div class="table-wrapper">
            <table>

                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Jenis</th>
                        <th>Item</th>
                        <th>Jumlah</th>
                        <th>Alasan</th>
                        <th>Nilai</th>
                        <th>Dicatat Oleh</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <template x-for="entry in filtered" :key="entry.id">
                        <tr>
                            <td x-text="entry.time"></td>
                            <td x-text="entry.type === 'menu' ? 'Menu' : 'Bahan'"></td>
                            <td x-text="entry.name"></td>
                            <td x-text="entry.qty"></td>
                            <td x-text="entry.reason"></td>
                            <td x-text="rupiah(entry.value)"></td>
                            <td x-text="entry.by"></td>
                            <td>
                                <button type="button" class="outline-button" @click="detail = entry">Lihat</button>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filtered.length === 0" x-cloak>
                        <td colspan="8" class="page-empty">Belum ada catatan waste yang cocok.</td>
                    </tr>

                </tbody>

            </table>
        </div>

    </div>


    <!-- =====================================================
         MODAL 1: FORM CATAT WASTE
    ====================================================== -->

    <div class="modal-backdrop" x-show="formOpen && !confirming" x-cloak @click.self="formOpen = false">

        <div class="modal-box wide" role="dialog" aria-modal="true" aria-labelledby="waste-form-title">

            <h4 id="waste-form-title">Catat Waste</h4>
            <p>Waktu dan pencatat terisi otomatis.</p>

            <form class="waste-form" @submit.prevent="attempt()" novalidate>

                <!-- JENIS -->
                <div class="form-field">
                    <span class="form-label">Jenis Waste</span>

                    <div class="segmented">
                        <button type="button" :class="{ 'active': form.type === 'bahan' }" @click="setType('bahan')">Barang</button>
                        <button type="button" :class="{ 'active': form.type === 'menu' }" @click="setType('menu')">Menu </button>
                    </div>
                </div>


                <!-- ===== JENIS: BAHAN ===== -->
                <div x-show="form.type === 'bahan'">

                    <div class="form-field">
                        <label class="form-label" for="waste-item">Barang *</label>

                        <select
                            id="waste-item"
                            class="form-input"
                            :class="{ 'has-error': submitted && errors.item }"
                            x-model="form.itemId"
                            @change="form.amount = ''; form.unitMode = 'pack'"
                        >
                            <option value="">Pilih barang</option>
                            <template x-for="item in items" :key="item.id">
                                <option :value="item.id" x-text="item.name"></option>
                            </template>
                        </select>

                        <p class="form-hint" x-show="selectedItem" x-text="selectedItem ? 'Stok tersedia: ' + display(selectedItem, selectedItem.stock) : ''"></p>
                        <p class="form-error" x-show="submitted && errors.item" x-text="errors.item" x-cloak></p>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="waste-amount">Jumlah *</label>

                        <div class="form-row">
                            <input
                                id="waste-amount"
                                type="number"
                                min="0"
                                step="any"
                                class="form-input"
                                :class="{ 'has-error': submitted && errors.amount }"
                                placeholder="0"
                                x-model.number="form.amount"
                            >

                            <select
                                class="form-input unit"
                                aria-label="Satuan"
                                x-show="selectedItem && selectedItem.size > 1"
                                x-model="form.unitMode"
                            >
                                <option value="pack" x-text="selectedItem ? selectedItem.pack : ''"></option>
                                <option value="unit" x-text="selectedItem ? selectedItem.unit : ''"></option>
                            </select>

                            <span class="form-unit" x-show="selectedItem && selectedItem.size <= 1" x-text="selectedItem ? selectedItem.pack : ''"></span>
                        </div>

                        <p class="form-hint" x-show="selectedItem && selectedItem.size > 1">
                            Pilih <strong x-text="selectedItem ? selectedItem.pack : ''"></strong> untuk satu kemasan utuh (misalnya botol pecah), atau
                            <strong x-text="selectedItem ? selectedItem.unit : ''"></strong> jika hanya sebagian yang terbuang.
                        </p>
                        <p class="form-error" x-show="submitted && errors.amount" x-text="errors.amount" x-cloak></p>
                    </div>

                </div>


                <!-- ===== JENIS: MENU ===== -->
                <div x-show="form.type === 'menu'" x-cloak>

                    <div class="form-field">
                        <label class="form-label" for="waste-menu">Menu *</label>

                        <select
                            id="waste-menu"
                            class="form-input"
                            :class="{ 'has-error': submitted && errors.menu }"
                            x-model="form.menuId"
                        >
                            <option value="">Pilih menu</option>
                            <template x-for="menu in menus" :key="menu.id">
                                <option :value="menu.id" x-text="menu.name"></option>
                            </template>
                        </select>

                        <p class="form-error" x-show="submitted && errors.menu" x-text="errors.menu" x-cloak></p>
                    </div>

                    <div class="form-field">
                        <label class="form-label" for="waste-portions">Jumlah Porsi *</label>

                        <input
                            id="waste-portions"
                            type="number"
                            min="1"
                            step="1"
                            class="form-input"
                            :class="{ 'has-error': submitted && errors.portions }"
                            x-model.number="form.portions"
                        >

                        <p class="form-error" x-show="submitted && errors.portions" x-text="errors.portions" x-cloak></p>
                    </div>

                </div>


                <!-- DAFTAR PENGURANGAN STOK (otomatis) -->
                <div class="summary-box" x-show="lines.length" x-cloak>

                    <p class="summary-title" x-text="form.type === 'menu' ? 'Bahan yang akan dikurangi sesuai resep' : 'Stok yang akan dikurangi'"></p>

                    <template x-for="line in lines" :key="line.item.id">
                        <div class="summary-line" :class="{ 'bad': !line.enough }">
                            <span x-text="line.item.name"></span>
                            <span x-text="format(line.need) + ' ' + line.item.unit + ' (stok: ' + display(line.item, line.item.stock) + ')'"></span>
                        </div>
                    </template>

                </div>


                <!-- ALASAN -->
                <div class="form-field">
                    <label class="form-label" for="waste-reason">Alasan *</label>

                    <select
                        id="waste-reason"
                        class="form-input"
                        :class="{ 'has-error': submitted && errors.reason }"
                        x-model="form.reason"
                    >
                        <option value="">Pilih alasan</option>
                        <template x-for="reason in reasons[form.type]" :key="reason">
                            <option :value="reason" x-text="reason"></option>
                        </template>
                    </select>

                    <p class="form-error" x-show="submitted && errors.reason" x-text="errors.reason" x-cloak></p>
                </div>


                <!-- CATATAN -->
                <div class="form-field">
                    <label class="form-label" for="waste-note">Catatan</label>

                    <textarea
                        id="waste-note"
                        class="form-input"
                        :class="{ 'has-error': submitted && errors.note }"
                        placeholder="Opsional (wajib jika alasan: Lainnya)"
                        x-model="form.note"
                    ></textarea>

                    <p class="form-error" x-show="submitted && errors.note" x-text="errors.note" x-cloak></p>
                </div>


                <!-- NILAI (otomatis) -->
                <div class="form-field">
                    <label class="form-label" for="waste-value">Nilai (otomatis)</label>

                    <input
                        id="waste-value"
                        type="text"
                        class="form-input"
                        readonly
                        :value="totalValue ? rupiah(totalValue) : '-'"
                    >
                </div>


                <div class="modal-actions">
                    <button type="button" class="outline-button btn-sm" @click="formOpen = false">Batal</button>
                    <button type="submit" class="primary-button btn-sm">Simpan</button>
                </div>

            </form>

        </div>

    </div>


    <!-- =====================================================
         MODAL 2: KONFIRMASI
    ====================================================== -->

    <div class="modal-backdrop" x-show="confirming" x-cloak @click.self="confirming = false">

        <div class="modal-box wide" role="dialog" aria-modal="true" aria-labelledby="waste-confirm-title">

            <h4 id="waste-confirm-title">Konfirmasi Pencatatan Waste</h4>
            <p>Periksa kembali sebelum dicatat.</p>

            <div class="summary-box">

                <div class="summary-line">
                    <span>Jenis</span>
                    <span x-text="form.type === 'menu' ? 'Menu (salah buat)' : 'Bahan / Barang'"></span>
                </div>

                <div class="summary-line">
                    <span x-text="form.type === 'menu' ? 'Menu' : 'Barang'"></span>
                    <span x-text="entryName"></span>
                </div>

                <div class="summary-line">
                    <span>Jumlah</span>
                    <span x-text="entryQty"></span>
                </div>

                <div class="summary-line">
                    <span>Alasan</span>
                    <span x-text="form.reason"></span>
                </div>

                <div class="summary-line">
                    <span>Nilai</span>
                    <span x-text="rupiah(totalValue)"></span>
                </div>

            </div>

            <div class="summary-box">

                <p class="summary-title">Perubahan stok cabang</p>

                <template x-for="line in lines" :key="line.item.id">
                    <div class="summary-line">
                        <span x-text="line.item.name"></span>
                        <span x-text="display(line.item, line.before) + ' → ' + display(line.item, line.after)"></span>
                    </div>
                </template>

            </div>

            <p class="info-note">Setelah dicatat, stok cabang akan berkurang dan alert waste dikirim ke Manager.</p>

            <div class="modal-actions">
                <button type="button" class="outline-button btn-sm" @click="confirming = false">Batal</button>
                <button type="button" class="primary-button btn-sm" @click="save()">Ya, Catat</button>
            </div>

        </div>

    </div>


    <!-- =====================================================
         MODAL 3: DETAIL WASTE
    ====================================================== -->

    <div class="modal-backdrop" x-show="detail" x-cloak @click.self="detail = null">

        <div class="modal-box wide" role="dialog" aria-modal="true" aria-labelledby="waste-detail-title">

            <template x-if="detail">

                <div>

                    <h4 id="waste-detail-title">Detail Waste</h4>
                    <p x-text="detail.time + ' · dicatat oleh ' + detail.by"></p>

                    <div class="summary-box">

                        <div class="summary-line">
                            <span>Jenis</span>
                            <span x-text="detail.type === 'menu' ? 'Menu (salah buat)' : 'Bahan / Barang'"></span>
                        </div>

                        <div class="summary-line">
                            <span x-text="detail.type === 'menu' ? 'Menu' : 'Barang'"></span>
                            <span x-text="detail.name"></span>
                        </div>

                        <div class="summary-line">
                            <span>Jumlah</span>
                            <span x-text="detail.qty"></span>
                        </div>

                        <div class="summary-line">
                            <span>Alasan</span>
                            <span x-text="detail.reason"></span>
                        </div>

                        <div class="summary-line">
                            <span>Catatan</span>
                            <span x-text="detail.note || '-'"></span>
                        </div>

                        <div class="summary-line">
                            <span>Nilai</span>
                            <span x-text="rupiah(detail.value)"></span>
                        </div>

                    </div>

                    <div class="summary-box">

                        <p class="summary-title">Pengurangan stok</p>

                        <template x-for="row in detail.deductions" :key="row.name">
                            <div class="summary-line">
                                <span x-text="row.name"></span>
                                <span x-text="row.text"></span>
                            </div>
                        </template>

                    </div>

                    <div class="modal-actions">
                        <button type="button" class="outline-button btn-sm" @click="detail = null">Tutup</button>
                    </div>

                </div>

            </template>

        </div>

    </div>

</section>

@endsection

@push('scripts')
<script>
    // DEMO: data dari blok @php di atas. Nanti diganti data dari database,
    // dan save() diganti request ke backend (stok berkurang + alert Manager dibuat di server).
    function wastePage() {

        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        const emptyForm = () => ({
            type: 'bahan',
            itemId: '',
            amount: '',
            unitMode: 'pack',   // 'pack' = per kemasan (Botol), 'unit' = satuan terkecil (ml)
            menuId: '',
            portions: 1,
            reason: '',
            note: '',
        });

        return {

            items: @json($items),
            menus: @json($menus),
            itemKeyToId: {},
            history: @json($history),
            by: @json($by),

            search: '',
            filterType: '',
            filterReason: '',

            form: emptyForm(),
            formOpen: false,
            confirming: false,
            submitted: false,
            detail: null,
            flash: '',

            reasons: {
                bahan: ['Rusak / Pecah', 'Kedaluwarsa', 'Tumpah', 'Lainnya'],
                menu:  ['Salah Buat', 'Tumpah', 'Tidak Sesuai Pesanan', 'Lainnya'],
            },


            /* ---------- FORMAT ---------- */

            format(value) {

                return new Intl.NumberFormat('id-ID').format(value);
            },


            rupiah(value) {

                return 'Rp ' + this.format(value);
            },


            // Ubah jumlah (satuan terkecil) menjadi tampilan per kemasan.
            // 3430 ml (isi 700) => "4 Botol 630 ml"
            display(item, amount) {

                if (amount <= 0) {
                    return '0 ' + item.pack;
                }

                if (item.size <= 1) {
                    return this.format(amount) + ' ' + item.pack;
                }

                const packs = Math.floor(amount / item.size);
                const rest  = Math.round((amount - packs * item.size) * 100) / 100;

                if (packs === 0) {
                    return this.format(rest) + ' ' + item.unit;
                }

                if (rest === 0) {
                    return this.format(packs) + ' ' + item.pack;
                }

                return this.format(packs) + ' ' + item.pack + ' ' + this.format(rest) + ' ' + item.unit;
            },


            /* ---------- RIWAYAT ---------- */

            get allReasons() {

                return [...new Set([...this.reasons.bahan, ...this.reasons.menu])];
            },


            get filtered() {

                const keyword = this.search.trim().toLowerCase();

                return this.history.filter(entry =>
                    (!keyword || entry.name.toLowerCase().includes(keyword))
                    && (!this.filterType || entry.type === this.filterType)
                    && (!this.filterReason || entry.reason === this.filterReason)
                );
            },


            /* ---------- BUKA / TUTUP ---------- */

            openForm() {

                this.form = emptyForm();
                this.submitted = false;
                this.confirming = false;
                this.flash = '';
                this.formOpen = true;
            },


            setType(type) {

                this.form.type = type;
                this.form.reason = '';
                this.submitted = false;
            },


            // Tombol Esc: tutup lapisan paling atas dulu
            closeTop() {

                if (this.confirming) {
                    this.confirming = false;
                } else if (this.detail) {
                    this.detail = null;
                } else {
                    this.formOpen = false;
                }
            },


            /* ---------- PERHITUNGAN ---------- */

            get selectedItem() {

                return this.items.find(item => item.id === this.form.itemId) ?? null;
            },


            get selectedMenu() {

                return this.menus.find(menu => menu.id === this.form.menuId) ?? null;
            },


            // Jumlah waste bahan dalam satuan terkecil (0 jika belum valid)
            get amountBase() {

                const item = this.selectedItem;
                const amount = this.form.amount;

                if (!item || typeof amount !== 'number' || amount <= 0) {
                    return 0;
                }

                const base = (item.size > 1 && this.form.unitMode === 'pack') ? amount * item.size : amount;

                return Math.round(base * 100) / 100;
            },


            // Daftar bahan yang akan dikurangi (1 baris untuk bahan, sesuai resep untuk menu)
            get lines() {

                let needs = [];

                if (this.form.type === 'bahan') {

                    if (this.selectedItem && this.amountBase > 0) {
                        needs = [[this.selectedItem, this.amountBase]];
                    }

                } else if (this.selectedMenu && Number.isInteger(this.form.portions) && this.form.portions >= 1) {

                    needs = Object.entries(this.selectedMenu.recipe)
                        .map(([id, qty]) => [this.items.find(item => item.id === id), qty * this.form.portions])
                        .filter(([item]) => item);
                }

                return needs.map(([item, need]) => ({
                    item,
                    need,
                    before: item.stock,
                    after: Math.round((item.stock - need) * 100) / 100,
                    enough: need <= item.stock,
                }));
            },


            // Nilai kerugian = jumlah x (harga per kemasan / isi kemasan)
            get totalValue() {

                return Math.round(
                    this.lines.reduce((sum, line) => sum + line.need * (line.item.price / line.item.size), 0)
                );
            },


            get entryName() {

                return this.form.type === 'menu'
                    ? (this.selectedMenu ? this.selectedMenu.name : '')
                    : (this.selectedItem ? this.selectedItem.name : '');
            },


            get entryQty() {

                if (this.form.type === 'menu') {
                    return this.form.portions + ' Porsi';
                }

                const item = this.selectedItem;

                if (!item) {
                    return '';
                }

                const label = (item.size > 1 && this.form.unitMode === 'unit') ? item.unit : item.pack;

                return this.format(this.form.amount) + ' ' + label;
            },


            /* ---------- VALIDASI ---------- */

            get errors() {

                const e = {};
                const f = this.form;

                if (f.type === 'bahan') {

                    if (!f.itemId) {
                        e.item = 'Pilih barang terlebih dahulu.';
                    }

                    if (typeof f.amount !== 'number' || f.amount <= 0) {
                        e.amount = 'Jumlah harus lebih dari 0.';
                    } else if (this.selectedItem && this.amountBase > this.selectedItem.stock) {
                        e.amount = 'Melebihi stok tersedia (' + this.display(this.selectedItem, this.selectedItem.stock) + ').';
                    }

                } else {

                    if (!f.menuId) {
                        e.menu = 'Pilih menu terlebih dahulu.';
                    }

                    if (!Number.isInteger(f.portions) || f.portions < 1) {
                        e.portions = 'Jumlah porsi minimal 1.';
                    } else if (this.lines.some(line => !line.enough)) {
                        e.portions = 'Stok bahan tidak mencukupi untuk jumlah porsi ini.';
                    }
                }

                if (!f.reason) {
                    e.reason = 'Pilih alasan.';
                }

                if (f.reason === 'Lainnya' && !f.note.trim()) {
                    e.note = 'Catatan wajib diisi jika alasan Lainnya.';
                }

                return e;
            },


            /* ---------- SIMPAN ---------- */

            attempt() {

                this.submitted = true;

                if (Object.keys(this.errors).length > 0) {
                    return;
                }

                this.confirming = true;
            },


            now() {

                const d = new Date();
                const pad = (n) => String(n).padStart(2, '0');

                return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear()
                    + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
            },


            // Muat katalog dari backend saat komponen diinisialisasi.
            async init() {
                try {
                    const [itemsRes, menusRes] = await Promise.all([
                        fetch('/ic/waste/items', {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                        }),
                        fetch('/ic/waste/menus', {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                        }),
                    ]);
                    if (itemsRes.ok) {
                        const itemsData = await itemsRes.json();
                        this.items = itemsData;
                        Object.entries(itemsData).forEach(([dbId, item]) => {
                            this.itemKeyToId[item.name] = dbId;
                        });
                    }
                    if (menusRes.ok) {
                        this.menus = await menusRes.json();
                    }
                } catch (e) {
                    console.warn('Gagal memuat katalog dari backend:', e);
                }
            },

            save() {
                const lines = this.lines;

                // Bangun payload untuk backend: 1 baris waste_log per bahan
                const itemsPayload = lines.map(line => {
                    const dbId = this.itemKeyToId[line.item.name] || line.item.id;
                    return {
                        bahan_id: dbId,
                        qty: line.need,
                        alasan: this.form.reason,
                        keterangan: this.form.note.trim() || null,
                    };
                });

                return fetch('/ic/waste', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ items: itemsPayload }),
                })
                .then(async r => {
                    const json = await r.json();
                    if (!r.ok) throw new Error(json.message || 'Gagal mencatat waste.');
                    return json;
                })
                .then(json => {
                    // Kurangi stok di tampilan
                    lines.forEach(line => { line.item.stock = line.after; });

                    const entry = {
                        id: Date.now(),
                        time: this.now(),
                        type: this.form.type,
                        name: this.entryName,
                        qty: this.entryQty,
                        reason: this.form.reason,
                        note: this.form.note.trim(),
                        value: this.totalValue,
                        by: this.by,
                        deductions: lines.map(line => ({
                            name: line.item.name,
                            text: this.format(line.need) + ' ' + line.item.unit
                                + ((line.item.size > 1 && line.need >= line.item.size)
                                    ? ' (' + this.display(line.item, line.need) + ')'
                                    : ''),
                        })),
                    };

                    this.history.unshift(entry);
                    this.confirming = false;
                    this.formOpen = false;
                    this.submitted = false;
                    this.form = Object.assign({}, this.form, { amount: '', portions: 1, note: '' });

                    this.flash = 'Waste berhasil dicatat. Stok cabang diperbarui.';
                    setTimeout(() => { this.flash = ''; }, 5000);
                })
                .catch(err => {
                    this.confirming = false;
                    this.flash = err.message || 'Gagal mencatat waste.';
                    setTimeout(() => { this.flash = ''; }, 5000);
                });
            },

        };
    }
</script>
@endpush