@extends('layouts.app')

@section('title', 'Penerimaan Barang')
@section('description', 'Cocokkan barang yang datang dengan Surat Jalan dan konfirmasi penerimaan.')

@php
    // DEMO: data dari config/demo.php (nanti diganti data dari database / controller).
    // DC mengirim KEMASAN UTUH, jadi sent / received dihitung dalam kemasan (Botol, Kantong, Pack, Pcs).
    // Saat dikonfirmasi, stok bertambah received x isi kemasan (dalam satuan terkecil ml / gr / Pcs).
    $items = collect(config('demo.items'))
        ->map(fn ($item, $id) => ['id' => $id] + $item)
        ->keyBy('id')
        ->all();

    $shipments = config('demo.shipments');
@endphp

@section('content')

<section class="page active" x-data="receivingPage()" @keydown.escape.window="confirming = false">

    <!-- =====================================================
         PESAN BERHASIL
    ====================================================== -->

    <div class="flash-success" x-show="flash" x-text="flash" x-cloak role="status"></div>


    <!-- =====================================================
         TAMPILAN 1: DAFTAR SURAT JALAN
    ====================================================== -->

    <div x-show="view === 'list'">

        <!-- RINGKASAN -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon">▱</div>
                <div class="stat-content">
                    <span class="stat-label">Dalam Pengiriman</span>
                    <strong x-text="count('dikirim')"></strong>
                    <small>barang masih di jalan</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">!</div>
                <div class="stat-content">
                    <span class="stat-label">Menunggu Pengecekan</span>
                    <strong x-text="count('menunggu')"></strong>
                    <small class="warning">perlu diperiksa IC</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <div class="stat-content">
                    <span class="stat-label">Sudah Diterima</span>
                    <strong x-text="count('diterima')"></strong>
                    <small class="positive">stok sudah bertambah</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">≠</div>
                <div class="stat-content">
                    <span class="stat-label">Ada Selisih</span>
                    <strong x-text="withDifference()"></strong>
                    <small class="danger-text">jumlah diterima berbeda</small>
                </div>
            </div>

        </div>


        <!-- TABEL SURAT JALAN -->
        <div class="page-card section-gap">

            <div class="table-toolbar">

                <input
                    type="search"
                    class="search-input"
                    placeholder="Cari no. surat jalan..."
                    aria-label="Cari nomor surat jalan"
                    x-model="search"
                >

                <select class="select" aria-label="Filter status" x-model="status">
                    <option value="">Semua Status</option>
                    <option value="dikirim">Dalam Pengiriman</option>
                    <option value="menunggu">Menunggu Pengecekan</option>
                    <option value="diterima">Diterima</option>
                </select>

                <span class="table-meta" x-text="'Menampilkan ' + filtered.length + ' dari ' + shipments.length + ' surat jalan'"></span>

            </div>

            <div class="table-wrapper">
                <table>

                    <thead>
                        <tr>
                            <th>No. Surat Jalan</th>
                            <th>Tanggal Kirim</th>
                            <th>Jumlah Item</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <template x-for="sj in filtered" :key="sj.no">
                            <tr>
                                <td x-text="sj.no"></td>
                                <td x-text="sj.date"></td>
                                <td x-text="sj.items.length + ' Item'"></td>
                                <td>
                                    <span
                                        class="status"
                                        :class="labels[sj.status].class"
                                        x-text="labels[sj.status].text"
                                    ></span>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="primary-button btn-sm"
                                        x-show="sj.status === 'menunggu'"
                                        @click="open(sj)"
                                    >Periksa</button>

                                    <button
                                        type="button"
                                        class="outline-button"
                                        x-show="sj.status === 'diterima'"
                                        @click="open(sj)"
                                    >Lihat</button>

                                    <span x-show="sj.status === 'dikirim'">-</span>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="filtered.length === 0" x-cloak>
                            <td colspan="5" class="page-empty">Tidak ada surat jalan yang cocok dengan pencarian.</td>
                        </tr>

                    </tbody>

                </table>
            </div>

        </div>

    </div>


    <!-- =====================================================
         TAMPILAN 2: PERIKSA / LIHAT PENERIMAAN
    ====================================================== -->

    <div x-show="view === 'detail'" x-cloak>

        <template x-if="current">

            <div>

                <button type="button" class="outline-button" @click="back()">← Kembali ke Daftar</button>

                <!-- INFO SURAT JALAN -->
                <div class="report-grid section-gap">

                    <div class="report-box">
                        <span>No. Surat Jalan</span>
                        <strong x-text="current.no"></strong>
                    </div>

                    <div class="report-box">
                        <span>Tanggal Kirim</span>
                        <strong x-text="current.date"></strong>
                    </div>

                    <div class="report-box">
                        <span>Asal</span>
                        <strong x-text="current.origin"></strong>
                    </div>

                    <div class="report-box">
                        <span>Status</span>
                        <strong x-text="labels[current.status].text"></strong>
                    </div>

                </div>


                <!-- DAFTAR BARANG -->
                <div class="page-card section-gap">

                    <div class="card-header">
                        <div>
                            <h3 x-text="editable ? 'Periksa Barang yang Diterima' : 'Rincian Barang Diterima'"></h3>
                            <p x-text="editable
                                ? 'Hitung kemasan yang tiba, lalu ubah angka jika ada yang berbeda dari Surat Jalan.'
                                : 'Penerimaan sudah dikonfirmasi dan tidak dapat diubah.'"></p>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table>

                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Barang</th>
                                    <th>Dikirim (sesuai SJ)</th>
                                    <th>Diterima</th>
                                    <th>Selisih</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>

                            <tbody>

                                <template x-for="(line, index) in current.items" :key="line.item">
                                    <tr>
                                        <td x-text="index + 1"></td>
                                        <td x-text="itemOf(line).name"></td>
                                        <td x-text="sentText(line)"></td>

                                        <!-- DITERIMA (kemasan utuh, bilangan bulat) -->
                                        <td>
                                            <template x-if="editable">
                                                <div class="form-row">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="1"
                                                        class="table-input qty"
                                                        :class="{ 'has-error': submitted && invalidQty(line) }"
                                                        :aria-label="'Jumlah diterima ' + itemOf(line).name"
                                                        x-model.number="line.received"
                                                    >
                                                    <span class="form-unit" x-text="itemOf(line).pack"></span>
                                                </div>
                                            </template>

                                            <span x-show="!editable" x-text="format(line.received) + ' ' + itemOf(line).pack"></span>
                                        </td>

                                        <!-- SELISIH -->
                                        <td>
                                            <span
                                                :class="{ 'danger-text': diff(line) !== null && diff(line) !== 0 }"
                                                x-text="diffText(line)"
                                            ></span>
                                        </td>

                                        <!-- CATATAN -->
                                        <td>
                                            <template x-if="editable">
                                                <input
                                                    type="text"
                                                    class="table-input"
                                                    :class="{ 'has-error': submitted && noteMissing(line) }"
                                                    :placeholder="needsNote(line) ? 'Wajib diisi (rusak, kurang, dll)' : '-'"
                                                    :aria-label="'Catatan ' + itemOf(line).name"
                                                    x-model="line.note"
                                                >
                                            </template>

                                            <span x-show="!editable" x-text="line.note || '-'"></span>
                                        </td>
                                    </tr>
                                </template>

                            </tbody>

                        </table>
                    </div>


                    <!-- INFO + TOMBOL (hanya saat memeriksa) -->
                    <div class="receive-footer" x-show="editable">

                        <p
                            class="info-note"
                            :class="{ 'error': submitted && hasErrors }"
                            x-text="submitted && hasErrors
                                ? 'Periksa kembali: isi jumlah diterima (bilangan bulat), dan catatan untuk barang yang selisihnya tidak 0.'
                                : 'DC mengirim kemasan utuh. Jika ada selisih (nilai tidak 0), kolom catatan wajib diisi.'"
                        ></p>

                        <div class="receive-actions">
                            <button type="button" class="outline-button btn-sm" @click="back()">Batal</button>
                            <button type="button" class="primary-button" @click="attempt()">✓ Konfirmasi Penerimaan</button>
                        </div>

                    </div>

                </div>

            </div>

        </template>

    </div>


    <!-- =====================================================
         DIALOG KONFIRMASI
    ====================================================== -->

    <div class="modal-backdrop" x-show="confirming" x-cloak @click.self="confirming = false">

        <div class="modal-box wide" role="dialog" aria-modal="true" aria-labelledby="confirm-title">

            <h4 id="confirm-title">Konfirmasi Penerimaan Barang</h4>

            <p>
                Setelah dikonfirmasi, stok cabang akan bertambah dan tidak dapat diubah lagi. Lanjutkan?
            </p>

            <div class="summary-box">

                <p class="summary-title">Perubahan stok cabang</p>

                <template x-for="change in stockChanges" :key="change.item.id">
                    <div class="summary-line">
                        <span x-text="change.item.name"></span>
                        <span x-text="display(change.item, change.before) + ' → ' + display(change.item, change.after)"></span>
                    </div>
                </template>

                <div class="summary-line" x-show="stockChanges.length === 0">
                    <span>Tidak ada barang yang diterima.</span>
                </div>

            </div>

            <div class="modal-actions">
                <button type="button" class="outline-button btn-sm" @click="confirming = false">Batal</button>
                <button type="button" class="primary-button btn-sm" @click="confirmReceipt()">Ya, Konfirmasi</button>
            </div>

        </div>

    </div>

</section>

@endsection

@push('scripts')
<script>
    // DEMO: data dari blok @php di atas. Nanti diganti data dari database,
    // dan confirmReceipt() diganti request ke backend (stok cabang bertambah di server).
    function receivingPage() {

        return {

            items: @json($items),          // barang, kunci = id barang
            shipments: @json($shipments),

            view: 'list',          // 'list' | 'detail'
            current: null,         // salinan surat jalan yang sedang dibuka

            search: '',
            status: '',

            submitted: false,
            confirming: false,
            flash: '',

            labels: {
                dikirim:  { text: 'Dalam Pengiriman',    class: 'warning' },
                menunggu: { text: 'Menunggu Pengecekan', class: 'info'    },
                diterima: { text: 'Diterima',            class: 'success' },
            },


            /* ---------- FORMAT ---------- */

            format(value) {

                return new Intl.NumberFormat('id-ID').format(value);
            },


            itemOf(line) {

                return this.items[line.item];
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


            // "10 Botol (7.000 ml)" untuk barang berkemasan, "300 Pcs" untuk barang satuan
            sentText(line) {

                const item = this.itemOf(line);

                if (item.size > 1) {
                    return this.format(line.sent) + ' ' + item.pack
                        + ' (' + this.format(line.sent * item.size) + ' ' + item.unit + ')';
                }

                return this.format(line.sent) + ' ' + item.pack;
            },


            /* ---------- DAFTAR ---------- */

            count(status) {

                return this.shipments.filter(sj => sj.status === status).length;
            },


            // Jumlah surat jalan yang sudah diterima tapi ada selisih
            withDifference() {

                return this.shipments.filter(sj =>
                    sj.status === 'diterima'
                    && sj.items.some(line => line.received !== line.sent)
                ).length;
            },


            get filtered() {

                const keyword = this.search.trim().toLowerCase();

                return this.shipments.filter(sj =>
                    (!keyword || sj.no.toLowerCase().includes(keyword))
                    && (!this.status || sj.status === this.status)
                );
            },


            /* ---------- BUKA / TUTUP DETAIL ---------- */

            open(sj) {

                // Salinan, supaya perubahan angka tidak langsung mengubah daftar
                this.current = JSON.parse(JSON.stringify(sj));

                // Default: jumlah diterima = jumlah dikirim (IC tinggal ubah jika berbeda)
                if (this.current.status === 'menunggu') {
                    this.current.items.forEach(line => {
                        if (line.received === null) {
                            line.received = line.sent;
                        }
                    });
                }

                this.submitted = false;
                this.flash = '';
                this.view = 'detail';

                window.scrollTo({ top: 0 });
            },


            back() {

                this.view = 'list';
                this.submitted = false;
                this.confirming = false;

                window.scrollTo({ top: 0 });
            },


            /* ---------- PEMERIKSAAN ---------- */

            get editable() {

                return this.current && this.current.status === 'menunggu';
            },


            // Kemasan utuh: harus bilangan bulat >= 0
            invalidQty(line) {

                return !Number.isInteger(line.received) || line.received < 0;
            },


            // Selisih = diterima - dikirim (null jika angka belum valid)
            diff(line) {

                if (this.invalidQty(line)) {
                    return null;
                }

                return line.received - line.sent;
            },


            diffText(line) {

                const value = this.diff(line);

                if (value === null) {
                    return '-';
                }

                if (value === 0) {
                    return '0';
                }

                return (value > 0 ? '+' : '') + this.format(value) + ' ' + this.itemOf(line).pack;
            },


            needsNote(line) {

                const value = this.diff(line);

                return value !== null && value !== 0;
            },


            noteMissing(line) {

                return this.needsNote(line) && !line.note.trim();
            },


            get hasErrors() {

                return this.current.items.some(line =>
                    this.invalidQty(line) || this.noteMissing(line)
                );
            },


            // Perubahan stok jika dikonfirmasi: stok + (diterima x isi kemasan)
            get stockChanges() {

                if (!this.current) {
                    return [];
                }

                return this.current.items
                    .filter(line => !this.invalidQty(line) && line.received > 0)
                    .map(line => {

                        const item = this.itemOf(line);

                        return {
                            item,
                            before: item.stock,
                            after: item.stock + line.received * item.size,
                        };
                    });
            },


            attempt() {

                this.submitted = true;

                if (this.hasErrors) {
                    return;
                }

                this.confirming = true;
            },


            confirmReceipt() {

                const target = this.shipments.find(sj => sj.no === this.current.no);

                // Tambah stok (di tampilan; di backend dilakukan server)
                this.current.items.forEach(line => {
                    if (!this.invalidQty(line)) {
                        const item = this.itemOf(line);
                        item.stock = item.stock + line.received * item.size;
                    }
                });

                target.status = 'diterima';
                target.items = JSON.parse(JSON.stringify(this.current.items));

                this.flash = 'Penerimaan ' + target.no + ' berhasil dikonfirmasi. Stok cabang sudah diperbarui.';

                setTimeout(() => { this.flash = ''; }, 5000);

                this.back();
            },

        };
    }
</script>
@endpush