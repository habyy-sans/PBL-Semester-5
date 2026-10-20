@extends('layouts.app')

@section('title', 'Pesanan Masuk')
@section('description', 'Pesanan yang sudah disetujui Manajer dan siap diproses.')

@php
    // Data dari OrderController@index (database), format sama dengan demo lama.
@endphp

@section('content')

<section class="page active" x-data="orderPage()" @keydown.escape.window="confirming = false">

    <!-- PESAN BERHASIL -->
    <div class="flash-success" x-show="flash" x-text="flash" x-cloak role="status"></div>


    <!-- =====================================================
         TAMPILAN 1: DAFTAR PESANAN
    ====================================================== -->

    <div x-show="view === 'list'">

        <!-- RINGKASAN -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon">!</div>
                <div class="stat-content">
                    <span class="stat-label">Pesanan Baru</span>
                    <strong x-text="count('baru')"></strong>
                    <small class="warning">perlu disiapkan DC</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">▱</div>
                <div class="stat-content">
                    <span class="stat-label">Sudah Dikirim</span>
                    <strong x-text="count('dikirim')"></strong>
                    <small class="positive">Surat Jalan terbit</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">≡</div>
                <div class="stat-content">
                    <span class="stat-label">Total Pesanan</span>
                    <strong x-text="orders.length"></strong>
                    <small x-text="stores.length + ' store'"></small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">↻</div>
                <div class="stat-content">
                    <span class="stat-label">Item Perlu Disiapkan</span>
                    <strong x-text="pendingItems()"></strong>
                    <small>dari pesanan baru</small>
                </div>
            </div>

        </div>


        <!-- TABEL PESANAN -->
        <div class="page-card section-gap">

            <div class="table-toolbar">

                <input
                    type="search"
                    class="search-input"
                    placeholder="Cari no. pesanan..."
                    aria-label="Cari nomor pesanan"
                    x-model="search"
                >

                <select class="select" aria-label="Filter store" x-model="store">
                    <option value="">Semua Store</option>
                    <template x-for="item in stores" :key="item.id">
                        <option :value="item.id" x-text="item.name"></option>
                    </template>
                </select>

                <select class="select" aria-label="Filter status" x-model="status">
                    <option value="">Semua Status</option>
                    <option value="baru">Baru</option>
                    <option value="dikirim">Dikirim</option>
                </select>

                <span class="table-meta" x-text="'Menampilkan ' + filtered.length + ' dari ' + orders.length + ' pesanan'"></span>

            </div>

            <div class="table-wrapper">
                <table>

                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Tanggal Disetujui</th>
                            <th>Tujuan</th>
                            <th>Jumlah Item</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <template x-for="order in filtered" :key="order.no">
                            <tr>
                                <td x-text="order.no"></td>
                                <td x-text="order.date"></td>
                                <td x-text="storeName(order.store)"></td>
                                <td x-text="order.lines.length + ' Item'"></td>
                                <td>
                                    <span
                                        class="status"
                                        :class="labels[order.status].class"
                                        x-text="labels[order.status].text"
                                    ></span>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="primary-button btn-sm"
                                        x-show="order.status === 'baru'"
                                        @click="open(order)"
                                    >Proses</button>

                                    <button
                                        type="button"
                                        class="outline-button"
                                        x-show="order.status === 'dikirim'"
                                        @click="open(order)"
                                    >Lihat</button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="filtered.length === 0" x-cloak>
                            <td colspan="6" class="page-empty">Tidak ada pesanan yang cocok dengan pencarian.</td>
                        </tr>

                    </tbody>

                </table>
            </div>

        </div>

    </div>


    <!-- =====================================================
         TAMPILAN 2: PROSES / LIHAT PESANAN
    ====================================================== -->

    <div x-show="view === 'detail'" x-cloak>

        <template x-if="current">

            <div>

                <button type="button" class="outline-button" @click="back()">← Kembali ke Daftar</button>

                <!-- INFO PESANAN -->
                <div class="report-grid section-gap">

                    <div class="report-box">
                        <span>No. Pesanan</span>
                        <strong x-text="current.no"></strong>
                    </div>

                    <div class="report-box">
                        <span>Tanggal Disetujui</span>
                        <strong x-text="current.date"></strong>
                    </div>

                    <div class="report-box">
                        <span>Tujuan</span>
                        <strong x-text="storeName(current.store)"></strong>
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
                            <h3 x-text="editable ? 'Siapkan Barang' : 'Rincian Pesanan'"></h3>
                            <p x-text="editable
                                ? 'Centang setiap barang yang sudah disiapkan, lalu terbitkan Surat Jalan.'
                                : 'Surat Jalan ' + current.shipment + ' sudah diterbitkan untuk pesanan ini.'"></p>
                        </div>

                        <button
                            type="button"
                            class="outline-button"
                            x-show="editable"
                            @click="toggleAll()"
                            x-text="allReady ? 'Batalkan Semua' : 'Tandai Semua Siap'"
                        ></button>
                    </div>

                    <div class="table-wrapper">
                        <table>

                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Barang</th>
                                    <th>Dipesan</th>
                                    <th>Stok DC</th>
                                    <th>Siap</th>
                                </tr>
                            </thead>

                            <tbody>

                                <template x-for="(line, index) in current.lines" :key="line.item">
                                    <tr>
                                        <td x-text="index + 1"></td>
                                        <td x-text="itemOf(line).name"></td>
                                        <td x-text="qtyText(line)"></td>
                                        <td x-text="format(dcStock[line.item]) + ' ' + itemOf(line).pack"></td>
                                        <td>
                                            <input
                                                type="checkbox"
                                                class="check-input"
                                                x-show="editable"
                                                :aria-label="'Siap: ' + itemOf(line).name"
                                                x-model="line.ready"
                                            >

                                            <span x-show="!editable">✓</span>
                                        </td>
                                    </tr>
                                </template>

                            </tbody>

                        </table>
                    </div>


                    <!-- INFO + TOMBOL (hanya saat memproses) -->
                    <div class="receive-footer" x-show="editable">

                        <p class="info-note" x-text="allReady
                            ? 'Semua barang sudah siap. Surat Jalan bisa diterbitkan.'
                            : 'Tombol Terbitkan Surat Jalan aktif setelah semua barang dicentang siap.'"></p>

                        <div class="receive-actions">
                            <button type="button" class="outline-button btn-sm" @click="back()">Batal</button>
                            <button type="button" class="primary-button" :disabled="!allReady" @click="confirming = true">Terbitkan Surat Jalan</button>
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

        <div class="modal-box wide" role="dialog" aria-modal="true" aria-labelledby="issue-title">

            <h4 id="issue-title">Terbitkan Surat Jalan</h4>

            <p>Surat Jalan akan dikirim ke store tujuan dan muncul di Penerimaan Barang IC. Lanjutkan?</p>

            <div class="summary-box" x-show="current">

                <div class="summary-line">
                    <span>No. Pesanan</span>
                    <span x-text="current ? current.no : ''"></span>
                </div>

                <div class="summary-line">
                    <span>Tujuan</span>
                    <span x-text="current ? storeName(current.store) : ''"></span>
                </div>

                <div class="summary-line">
                    <span>Jumlah Item</span>
                    <span x-text="current ? current.lines.length + ' Item' : ''"></span>
                </div>

                <div class="summary-line">
                    <span>No. Surat Jalan</span>
                    <span class="muted">No. SJ dibuat otomatis oleh sistem</span>
                </div>

            </div>

            <div class="modal-actions">
                <button type="button" class="outline-button btn-sm" @click="confirming = false">Batal</button>
                <button type="button" class="primary-button btn-sm" @click="issue()">Ya, Terbitkan</button>
            </div>

        </div>

    </div>

</section>

@endsection

@push('scripts')
<script>
    // DEMO: data dari blok @php di atas. Nanti diganti data dari database,
    // dan issue() diganti request ke backend (Surat Jalan dibuat di server).
    function orderPage() {

        return {

            items: @json($items),          // barang, kunci = id barang
            stores: @json($storeList),
            orders: @json($orders),
            dcStock: @json($dcStock),      // stok DC per barang, dalam kemasan
            nextNo: @json($nextShipment),

            view: 'list',          // 'list' | 'detail'
            current: null,         // salinan pesanan yang sedang dibuka

            search: '',
            store: '',
            status: '',

            confirming: false,
            flash: '',

            labels: {
                baru:    { text: 'Baru',    class: 'info'    },
                dikirim: { text: 'Dikirim', class: 'success' },
            },


            /* ---------- FORMAT ---------- */

            format(value) {

                return new Intl.NumberFormat('id-ID').format(value);
            },


            itemOf(line) {

                return this.items[line.item];
            },


            storeName(id) {

                const found = this.stores.find(item => item.id === id);

                return found ? found.name : id;
            },


            // "8 Botol (5.600 ml)" untuk barang berkemasan, "300 Pcs" untuk barang satuan
            qtyText(line) {

                const item = this.itemOf(line);

                if (item.size > 1) {
                    return this.format(line.qty) + ' ' + item.pack
                        + ' (' + this.format(line.qty * item.size) + ' ' + item.unit + ')';
                }

                return this.format(line.qty) + ' ' + item.pack;
            },


            /* ---------- DAFTAR ---------- */

            count(status) {

                return this.orders.filter(order => order.status === status).length;
            },


            // Jumlah baris barang dari semua pesanan baru
            pendingItems() {

                return this.orders
                    .filter(order => order.status === 'baru')
                    .reduce((sum, order) => sum + order.lines.length, 0);
            },


            get filtered() {

                const keyword = this.search.trim().toLowerCase();

                return this.orders.filter(order =>
                    (!keyword || order.no.toLowerCase().includes(keyword))
                    && (!this.store || order.store === this.store)
                    && (!this.status || order.status === this.status)
                );
            },


            /* ---------- BUKA / TUTUP DETAIL ---------- */

            open(order) {

                // Salinan, supaya centang tidak langsung mengubah daftar
                this.current = JSON.parse(JSON.stringify(order));

                this.current.lines.forEach(line => { line.ready = false; });

                this.flash = '';
                this.view = 'detail';

                window.scrollTo({ top: 0 });
            },


            back() {

                this.view = 'list';
                this.confirming = false;

                window.scrollTo({ top: 0 });
            },


            /* ---------- PERSIAPAN BARANG ---------- */

            get editable() {

                return this.current && this.current.status === 'baru';
            },


            get allReady() {

                return !!this.current
                    && this.current.lines.length > 0
                    && this.current.lines.every(line => line.ready);
            },


            toggleAll() {

                const target = !this.allReady;

                this.current.lines.forEach(line => { line.ready = target; });
            },


            // Nomor Surat Jalan dibuat oleh server (tidak lagi dihitung di tampilan).
            followingNo(no) {

                return no;
            },


            async issue() {

                if (!this.allReady) {
                    return;
                }

                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const order = this.current;
                const target = this.orders.find(o => o.no === order.no);

                try {
                    const res = await fetch('/dc/shipment', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                        body: JSON.stringify({
                            draft_id: order.id,
                            items: order.lines.map(line => ({ bahan_id: Number(line.item), qty_dikirim: line.qty })),
                        }),
                    });

                    const json = await res.json().catch(() => ({}));

                    if (!res.ok) {
                        this.flash = json.message || 'Gagal menerbitkan Surat Jalan.';
                        this.confirming = false;
                        return;
                    }

                    const issued = json.no;

                    target.status = 'dikirim';
                    target.shipment = issued;

                    // Langsung tandai dikirim agar IC bisa memeriksa.
                    await fetch(`/dc/shipment/${json.surat_jalan_id}/kirim`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                    });

                    this.flash = 'Surat Jalan ' + issued + ' untuk pesanan ' + target.no
                        + ' berhasil diterbitkan dan dikirim ke ' + this.storeName(target.store) + '.';

                    setTimeout(() => { this.flash = ''; }, 6000);

                    this.back();
                } catch (e) {
                    this.flash = 'Gagal menerbitkan Surat Jalan. Periksa koneksi lalu coba lagi.';
                    this.confirming = false;
                }
            },

        };
    }
</script>
@endpush