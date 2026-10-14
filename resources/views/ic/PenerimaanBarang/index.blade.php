@extends('layouts.app')

@section('title', 'Penerimaan Barang')
@section('description', 'Cocokkan barang yang datang dengan Surat Jalan dan konfirmasi penerimaan.')

@php
    // DEMO: data contoh untuk frontend. Nanti diganti data dari database (controller).
    // status: dikirim (Dalam Pengiriman) | menunggu (Menunggu Pengecekan) | diterima
    // sent = jumlah sesuai Surat Jalan, received = jumlah yang diterima IC (null jika belum diperiksa)
    $shipments = [
        [
            'no' => '#SJ-018', 'date' => '14 Okt 2026', 'origin' => 'Distribution Center',
            'status' => 'dikirim', 'itemCount' => 12, 'items' => [],
        ],
        [
            'no' => '#SJ-016', 'date' => '13 Okt 2026', 'origin' => 'Distribution Center',
            'status' => 'menunggu', 'itemCount' => 5, 'items' => [
                ['name' => 'Fresh Milk',     'unit' => 'Liter', 'sent' => 20,  'received' => null, 'note' => ''],
                ['name' => 'Sirup Vanilla',  'unit' => 'Botol', 'sent' => 10,  'received' => null, 'note' => ''],
                ['name' => 'Sirup Hazelnut', 'unit' => 'Botol', 'sent' => 10,  'received' => null, 'note' => ''],
                ['name' => 'Cup 16 oz',      'unit' => 'Pcs',   'sent' => 300, 'received' => null, 'note' => ''],
                ['name' => 'Tutup Cup',      'unit' => 'Pcs',   'sent' => 300, 'received' => null, 'note' => ''],
            ],
        ],
        [
            'no' => '#SJ-015', 'date' => '11 Okt 2026', 'origin' => 'Distribution Center',
            'status' => 'diterima', 'itemCount' => 3, 'items' => [
                ['name' => 'Robusta Beans', 'unit' => 'Kg', 'sent' => 10, 'received' => 10, 'note' => ''],
                ['name' => 'Bubuk Matcha',  'unit' => 'Kg', 'sent' => 5,  'received' => 5,  'note' => ''],
                ['name' => 'Bubuk Cokelat', 'unit' => 'Kg', 'sent' => 8,  'received' => 8,  'note' => ''],
            ],
        ],
        [
            'no' => '#SJ-014', 'date' => '9 Okt 2026', 'origin' => 'Distribution Center',
            'status' => 'diterima', 'itemCount' => 4, 'items' => [
                ['name' => 'Fresh Milk',    'unit' => 'Liter', 'sent' => 20, 'received' => 18, 'note' => 'Kemasan bocor saat pengiriman'],
                ['name' => 'Gula Pasir',    'unit' => 'Kg',    'sent' => 10, 'received' => 10, 'note' => ''],
                ['name' => 'Arabica Beans', 'unit' => 'Kg',    'sent' => 12, 'received' => 12, 'note' => ''],
                ['name' => 'Sirup Caramel', 'unit' => 'Botol', 'sent' => 6,  'received' => 6,  'note' => ''],
            ],
        ],
        [
            'no' => '#SJ-012', 'date' => '6 Okt 2026', 'origin' => 'Distribution Center',
            'status' => 'diterima', 'itemCount' => 2, 'items' => [
                ['name' => 'Cup 16 oz',  'unit' => 'Pcs', 'sent' => 200, 'received' => 200, 'note' => ''],
                ['name' => 'Tutup Cup',  'unit' => 'Pcs', 'sent' => 200, 'received' => 200, 'note' => ''],
            ],
        ],
    ];
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
                                <td x-text="countOf(sj) + ' Item'"></td>
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
                                ? 'Cocokkan jumlah fisik dengan Surat Jalan, lalu ubah angka jika ada yang berbeda.'
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

                                <template x-for="(item, index) in current.items" :key="item.name">
                                    <tr>
                                        <td x-text="index + 1"></td>
                                        <td x-text="item.name"></td>
                                        <td x-text="format(item.sent) + ' ' + item.unit"></td>

                                        <!-- DITERIMA -->
                                        <td>
                                            <template x-if="editable">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="any"
                                                    class="table-input qty"
                                                    :class="{ 'has-error': submitted && invalidQty(item) }"
                                                    :aria-label="'Jumlah diterima ' + item.name"
                                                    x-model.number="item.received"
                                                >
                                            </template>

                                            <span x-show="!editable" x-text="format(item.received) + ' ' + item.unit"></span>
                                        </td>

                                        <!-- SELISIH -->
                                        <td>
                                            <span
                                                :class="{ 'danger-text': diff(item) !== 0 && diff(item) !== null }"
                                                x-text="diff(item) === null ? '-' : (diff(item) > 0 ? '+' : '') + format(diff(item))"
                                            ></span>
                                        </td>

                                        <!-- CATATAN -->
                                        <td>
                                            <template x-if="editable">
                                                <input
                                                    type="text"
                                                    class="table-input"
                                                    :class="{ 'has-error': submitted && noteMissing(item) }"
                                                    :placeholder="needsNote(item) ? 'Wajib diisi (rusak, kurang, dll)' : '-'"
                                                    :aria-label="'Catatan ' + item.name"
                                                    x-model="item.note"
                                                >
                                            </template>

                                            <span x-show="!editable" x-text="item.note || '-'"></span>
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
                                ? 'Periksa kembali: isi jumlah diterima, dan catatan untuk barang yang selisihnya tidak 0.'
                                : 'Jika ada selisih (nilai tidak 0), kolom catatan wajib diisi.'"
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

        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="confirm-title">

            <h4 id="confirm-title">Konfirmasi Penerimaan Barang</h4>

            <p>
                Setelah dikonfirmasi, stok cabang akan bertambah dan tidak dapat diubah lagi. Lanjutkan?
            </p>

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
    // DEMO: data dari $shipments di atas. Nanti diganti data dari database,
    // dan confirmReceipt() diganti request ke backend (stok cabang bertambah di server).
    function receivingPage() {

        return {

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


            /* ---------- DAFTAR ---------- */

            countOf(sj) {

                return sj.items.length || sj.itemCount;
            },


            count(status) {

                return this.shipments.filter(sj => sj.status === status).length;
            },


            // Jumlah surat jalan yang sudah diterima tapi ada selisih
            withDifference() {

                return this.shipments.filter(sj =>
                    sj.status === 'diterima'
                    && sj.items.some(item => item.received !== item.sent)
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
                    this.current.items.forEach(item => {
                        if (item.received === null) {
                            item.received = item.sent;
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


            invalidQty(item) {

                return typeof item.received !== 'number' || item.received < 0;
            },


            // Selisih = diterima - dikirim (null jika angka belum valid)
            diff(item) {

                if (this.invalidQty(item)) {
                    return null;
                }

                return Math.round((item.received - item.sent) * 100) / 100;
            },


            needsNote(item) {

                const value = this.diff(item);

                return value !== null && value !== 0;
            },


            noteMissing(item) {

                return this.needsNote(item) && !item.note.trim();
            },


            get hasErrors() {

                return this.current.items.some(item =>
                    this.invalidQty(item) || this.noteMissing(item)
                );
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

                target.status = 'diterima';
                target.items = JSON.parse(JSON.stringify(this.current.items));

                this.flash = 'Penerimaan ' + target.no + ' berhasil dikonfirmasi. Stok cabang sudah diperbarui.';

                setTimeout(() => { this.flash = ''; }, 5000);

                this.back();
            },


            format(value) {

                return new Intl.NumberFormat('id-ID').format(value);
            },

        };
    }
</script>
@endpush