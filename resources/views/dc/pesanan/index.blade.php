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
                <div class="stat-icon">↻</div>
                <div class="stat-content">
                    <span class="stat-label">Pengiriman Parsial</span>
                    <strong x-text="count('diproses')"></strong>
                    <small>masih ada sisa pesanan</small>
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
                    <option value="diproses">Diproses</option>
                    <option value="selesai">Selesai</option>
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
                                        x-show="['baru', 'diproses'].includes(order.status)"
                                        @click="open(order)"
                                    >Proses</button>

                                    <button
                                        type="button"
                                        class="outline-button"
                                        x-show="order.status === 'selesai'"
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
                            <h3 x-text="editable ? 'Rincian Pengiriman' : 'Rincian Pesanan'"></h3>
                            <p x-text="editable
                                ? 'Masukkan jumlah barang yang akan dikirim. Pengiriman dapat dilakukan bertahap.'
                                : 'Seluruh kuantitas pesanan telah dikirim.'"></p>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Barang</th>
                                    <th>Dipesan</th>
                                    <th>Sudah Dikirim</th>
                                    <th>Sisa</th>
                                    <th>Stok DC</th>
                                    <th x-show="editable">Kirim Sekarang</th>
                                </tr>
                            </thead>

                            <tbody>
                                <template x-for="(line, index) in current.lines" :key="line.detail_draft_id">
                                    <tr>
                                        <td x-text="index + 1"></td>
                                        <td x-text="itemOf(line).name"></td>

                                        <td x-text="quantityText(line.qty_requested_base, itemOf(line))"></td>

                                        <td x-text="quantityText(line.qty_sent_base, itemOf(line))"></td>

                                        <td x-text="quantityText(line.qty_remaining_base, itemOf(line))"></td>

                                        <td x-text="quantityText(line.stock_base, itemOf(line))"></td>

                                        <td x-show="editable">
                                            <input
                                                type="number"
                                                class="form-input"
                                                style="max-width: 140px;"
                                                min="0"
                                                step="0.001"
                                                :max="Math.min(line.qty_remaining_base, line.stock_base)"
                                                x-model.number="line.qty_to_ship_base"
                                                :disabled="line.qty_remaining_base <= 0"
                                                :aria-label="'Jumlah dikirim untuk ' + itemOf(line).name"
                                            >
                                            <small x-text="itemOf(line).unit"></small>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="section-gap" x-show="editable">

                        <label for="shipment-note" class="form-label">
                            Catatan Pengiriman
                        </label>

                        <textarea
                            id="shipment-note"
                            class="form-input"
                            rows="3"
                            x-model.trim="catatan"
                            placeholder="Wajib diisi jika pengiriman belum memenuhi seluruh sisa pesanan."
                        ></textarea>

                        <p class="info-note" style="margin-top: 8px;"
                        x-text="partialShipment
                            ? 'Pengiriman belum memenuhi seluruh pesanan. Catatan wajib diisi.'
                            : 'Pengiriman memenuhi seluruh sisa pesanan. Catatan bersifat opsional.'">
                        </p>

                        <p class="warning"
                        x-show="quantityError"
                        x-text="quantityError"
                        style="margin-top: 8px;">
                        </p>

                    </div>

                    <div class="receive-footer" x-show="editable">

                        <p class="info-note">
                            Stok DC akan berkurang ketika Surat Jalan berhasil dibuat.
                            Pengiriman dapat dilakukan secara bertahap sesuai stok yang tersedia.
                        </p>

                        <div class="receive-actions">
                            <button
                                type="button"
                                class="outline-button btn-sm"
                                @click="back()"
                            >Batal</button>

                            <button
                                type="button"
                                class="primary-button"
                                :disabled="!canIssue"
                                @click="confirming = true"
                            >Terbitkan Surat Jalan</button>
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

            <p>
                Surat Jalan akan dibuat berdasarkan jumlah barang yang kamu masukkan.
                Stok DC langsung dikurangi setelah transaksi berhasil.
            </p>

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
                    <span x-text="current
                    ? current.lines.filter(line => Number(line.qty_to_ship_base) > 0).length + ' Item'
                    : ''"></span>
                </div>

                <div class="summary-line">
                    <span>No. Surat Jalan</span>
                    <span class="muted">No. SJ dibuat otomatis oleh sistem</span>
                </div>

            </div>

            <div class="modal-actions">
                <button type="button" class="outline-button btn-sm" @click="confirming = false">Batal</button>
                <button
                    type="button"
                    class="primary-button btn-sm"
                    :disabled="!canIssue || submitting"
                    @click="issue()"
                >
                    <span x-text="submitting ? 'Memproses...' : 'Ya, Terbitkan'"></span>
                </button>
            </div>

        </div>

    </div>

</section>

@endsection

@push('scripts')
<script>
    // Data pesanan dan stok berasal dari database melalui OrderController.
    function orderPage() {

        return {

            items: @json($items),
            stores: @json($storeList),
            orders: @json($orders),
            dcStock: @json($dcStock),

            init() {
                const requestedId = Number(
                    new URLSearchParams(window.location.search).get('draft_id')
                );

                const order = this.orders.find(item => item.id === requestedId);

                if (order) {
                    this.open(order);
                }
            },

            view: 'list',          // 'list' | 'detail'
            current: null,         // salinan pesanan yang sedang dibuka

            search: '',
            store: '',
            status: '',

            confirming: false,
            flash: '',

            catatan: '',
            submitting: false,

            labels: {
                baru: {
                    text: 'Baru',
                    class: 'info'
                },
                diproses: {
                    text: 'Diproses',
                    class: 'warning'
                },
                selesai: {
                    text: 'Selesai',
                    class: 'success'
                },
            },

            /* ---------- FORMAT ---------- */

            format(value) {

                return new Intl.NumberFormat('id-ID').format(value);
            },


            itemOf(line) {

                return this.items[line.item];
            },


            storeName(id) {
                const found = this.stores.find(
                    item => String(item.id) === String(id)
                );

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

            quantityText(quantity, item) {
                return this.format(quantity) + ' ' + (item.unit || 'Unit');
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
                    && (!this.store || String(order.store) === String(this.store))
                    && (!this.status || order.status === this.status)
                );
            },

            /* ---------- BUKA / TUTUP DETAIL ---------- */

            open(order) {
                this.current = JSON.parse(JSON.stringify(order));

                this.current.lines.forEach(line => {
                    line.qty_to_ship_base = Math.min(
                        Number(line.qty_remaining_base || 0),
                        Number(line.stock_base || 0)
                    );
                });

                this.catatan = '';
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
                return !!this.current &&
                    ['baru', 'diproses'].includes(this.current.status);
            },

            get partialShipment() {
                if (!this.current) return false;

                return this.current.lines.some(line => {
                    const remainingAfter =
                        Number(line.qty_remaining_base || 0) -
                        Number(line.qty_to_ship_base || 0);

                    return remainingAfter > 0.000001;
                });
            },

            get quantityError() {
                if (!this.current) return '';

                for (const line of this.current.lines) {
                    const qty = Number(line.qty_to_ship_base || 0);
                    const remaining = Number(line.qty_remaining_base || 0);
                    const stock = Number(line.stock_base || 0);

                    if (!Number.isFinite(qty) || qty < 0) {
                        return 'Jumlah pengiriman tidak valid.';
                    }

                    if (qty > remaining + 0.000001) {
                        return 'Jumlah pengiriman melebihi sisa pesanan.';
                    }

                    if (qty > stock + 0.000001) {
                        return 'Jumlah pengiriman melebihi stok DC.';
                    }
                }

                return '';
            },

            get canIssue() {
                if (
                    !this.editable ||
                    this.submitting ||
                    this.quantityError
                ) {
                    return false;
                }

                const hasQty = this.current.lines.some(line =>
                    Number(line.qty_to_ship_base || 0) > 0
                );

                return hasQty &&
                    (!this.partialShipment || this.catatan.trim().length > 0);
            },

            // Nomor Surat Jalan dibuat oleh server (tidak lagi dihitung di tampilan).
            followingNo(no) {

                return no;
            },


            async issue() {
                if (!this.canIssue) return;

                const token =
                    document.querySelector('meta[name="csrf-token"]')?.content || '';

                const order = this.current;

                const target = this.orders.find(
                    item => item.id === order.id
                );

                const shipmentItems = order.lines
                    .filter(line => Number(line.qty_to_ship_base || 0) > 0)
                    .map(line => ({
                        detail_draft_id: Number(line.detail_draft_id),
                        bahan_id: Number(line.item),
                        qty_dikirim: Number(line.qty_to_ship_base),
                    }));

                this.submitting = true;

                try {
                    const response = await fetch('/dc/shipment', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            draft_id: order.id,
                            items: shipmentItems,
                            catatan: this.catatan.trim() || null,
                        }),
                    });

                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const firstError = result.errors
                            ? Object.values(result.errors).flat()[0]
                            : null;

                        this.flash = firstError ||
                            result.message ||
                            'Gagal menerbitkan Surat Jalan.';

                        this.confirming = false;
                        return;
                    }

                    if (target) {
                        const sentMap = new Map(
                            shipmentItems.map(item => [
                                Number(item.detail_draft_id),
                                item.qty_dikirim
                            ])
                        );

                        target.lines.forEach(line => {
                            const newlySent = Number(
                                sentMap.get(Number(line.detail_draft_id)) || 0
                            );

                            line.qty_sent_base =
                                Number(line.qty_sent_base || 0) + newlySent;

                            line.qty_remaining_base = Math.max(
                                0,
                                Number(line.qty_remaining_base || 0) - newlySent
                            );

                            line.qty_to_ship_base = line.qty_remaining_base;

                            line.stock_base = Math.max(
                                0,
                                Number(line.stock_base || 0) - newlySent
                            );
                        });

                        target.status = target.lines.some(line =>
                            Number(line.qty_remaining_base || 0) > 0.000001
                        ) ? 'diproses' : 'selesai';

                        target.shipment = result.no || target.shipment;
                    }

                    this.confirming = false;
                    this.view = 'list';

                    this.flash =
                        'Surat Jalan ' + (result.no || '') +
                        ' berhasil dibuat. Stok DC telah dikurangi. ' +
                        'Silakan tandai Surat Jalan sebagai dikirim pada halaman Pengiriman setelah barang diberangkatkan.';

                    this.catatan = '';

                    window.scrollTo({ top: 0 });

                } catch (error) {
                    this.flash =
                        'Gagal menghubungi server. Periksa koneksi lalu coba lagi.';

                    this.confirming = false;

                } finally {
                    this.submitting = false;
                }
            },

        };
    }
</script>
@endpush