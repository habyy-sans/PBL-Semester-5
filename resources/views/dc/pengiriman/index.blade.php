@extends('layouts.app')

@section('title', 'Pengiriman & Surat Jalan')
@section('description', 'Proses pengiriman, Surat Jalan digital, dan status pengiriman.')

@php
    // Data dari ShipmentController@index (database), format sama dengan demo lama.
@endphp

@section('content')

<section class="page active" x-data="shipmentPage()">

    <!-- =====================================================
         TAMPILAN 1: DAFTAR SURAT JALAN
    ====================================================== -->

    <div x-show="view === 'list'">

        <!-- RINGKASAN -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon">▱</div>
                <span class="stat-label">Disiapkan DC</span>
                <strong x-text="count('disiapkan')"></strong>
                <small>menunggu keberangkatan</small>
            </div>

            <div class="stat-card">
                <div class="stat-icon">!</div>
                <div class="stat-content">
                    <span class="stat-label">Menunggu Pengecekan</span>
                    <strong x-text="count('menunggu')"></strong>
                    <small class="warning">menunggu IC store</small>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <div class="stat-content">
                    <span class="stat-label">Sudah Diterima</span>
                    <strong x-text="count('diterima')"></strong>
                    <small class="positive">dikonfirmasi IC</small>
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

                <select class="select" aria-label="Filter store" x-model="store">
                    <option value="">Semua Store</option>
                    <template x-for="item in stores" :key="item.id">
                        <option :value="item.id" x-text="item.name"></option>
                    </template>
                </select>

                <select class="select" aria-label="Filter status" x-model="status">
                    <option value="">Semua Status</option>
                    <option value="disiapkan">Disiapkan</option>
                    <option value="menunggu">Menunggu Pengecekan</option>
                    <option value="diterima">Diterima</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>

                <span class="table-meta" x-text="'Menampilkan ' + filtered.length + ' dari ' + shipments.length + ' surat jalan'"></span>

            </div>

            <div class="table-wrapper">
                <table>

                    <thead>
                        <tr>
                            <th>No. Surat Jalan</th>
                            <th>Tanggal Kirim</th>
                            <th>Tujuan</th>
                            <th>No. Pesanan</th>
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
                                <td x-text="storeName(sj.store)"></td>
                                <td x-text="sj.order"></td>
                                <td x-text="sj.items.length + ' Item'"></td>
                                <td>
                                    <span
                                        class="status"
                                        :class="labels[sj.status].class"
                                        x-text="labels[sj.status].text"
                                    ></span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <button
                                            type="button"
                                            class="outline-button btn-sm"
                                            @click="open(sj)"
                                        >
                                            Lihat
                                        </button>

                                        <button
                                            type="button"
                                            class="primary-button btn-sm"
                                            x-show="sj.status === 'disiapkan'"
                                            :disabled="sending || cancellingId === sj.id"
                                            @click="dispatchShipment(sj)"
                                        >
                                            Kirim
                                        </button>

                                        <button
                                            type="button"
                                            class="outline-button btn-sm"
                                            style="color: #dc2626; border-color: #dc2626;"
                                            x-show="sj.status === 'disiapkan'"
                                            :disabled="sending || cancellingId === sj.id"
                                            @click="cancelShipment(sj)"
                                            x-text="cancellingId === sj.id ? 'Membatalkan...' : 'Batalkan'"
                                        ></button>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="filtered.length === 0" x-cloak>
                            <td colspan="7" class="page-empty">Tidak ada surat jalan yang cocok dengan pencarian.</td>
                        </tr>

                    </tbody>

                </table>
            </div>

        </div>

    </div>


    <!-- =====================================================
         TAMPILAN 2: DOKUMEN SURAT JALAN DIGITAL
    ====================================================== -->

    <div x-show="view === 'detail'" x-cloak>

        <template x-if="current">

            <div>

                <div class="no-print">
                    <button type="button" class="outline-button" @click="back()">← Kembali ke Daftar</button>
                </div>

                <div class="page-card section-gap sj-document">

                    <!-- HEADER DOKUMEN -->
                    <div class="sj-header">
                        <div class="sj-company">
                            <div class="sj-company-mark">KS</div>
                            <div>
                                <h2>KOPSYNC</h2>
                                <p>SISTEM INFORMASI DISTRIBUSI DAN PERSEDIAAN</p>
                            </div>
                        </div>

                        <div class="sj-title">
                            <h1>SURAT JALAN</h1>
                            <strong x-text="current.no"></strong>
                        </div>
                    </div>

                    <div class="sj-divider"></div>

                    <!-- TOMBOL AKSI -->
                    <div class="sj-actions no-print">
                        <button
                            type="button"
                            class="outline-button"
                            @click="back()"
                        >
                            ← Kembali ke Daftar
                        </button>

                        <div class="sj-action-right">
                            <button
                                type="button"
                                class="primary-button btn-sm"
                                x-show="current.status === 'disiapkan'"
                                :disabled="sending"
                                @click="dispatchShipment(current)"
                                x-text="sending ? 'Memproses...' : 'Kirim Sekarang'"
                            ></button>

                            <button
                                type="button"
                                class="outline-button btn-sm"
                                style="color: #dc2626; border-color: #dc2626;"
                                x-show="current.status === 'disiapkan'"
                                :disabled="sending || cancellingId === current.id"
                                @click="cancelShipment(current)"
                                x-text="cancellingId === current.id ? 'Membatalkan...' : 'Batalkan Surat Jalan'"
                            ></button>

                            <button
                                type="button"
                                class="primary-button btn-sm"
                                @click="window.print()"
                            >
                                <icon name="printer" /> Cetak / Simpan PDF
                            </button>
                        </div>
                    </div>

                    <!-- IDENTITAS PENGIRIM DAN PENERIMA -->
                    <div class="sj-parties">

                        <div class="sj-party">
                            <span class="sj-label">PENGIRIM</span>
                            <h3 x-text="current.dc_name"></h3>
                            <p x-text="current.dc_address"></p>
                            <p>Telepon: <span x-text="current.dc_phone"></span></p>
                        </div>

                        <div class="sj-party">
                            <span class="sj-label">PENERIMA</span>
                            <h3 x-text="current.branch_name"></h3>
                            <p x-text="current.branch_address"></p>
                            <p>Telepon: <span x-text="current.branch_phone"></span></p>
                        </div>

                    </div>

                    <!-- INFORMASI DOKUMEN -->
                    <div class="sj-meta">
                        <div>
                            <span>Nomor Surat Jalan</span>
                            <strong x-text="current.no"></strong>
                        </div>

                        <div>
                            <span>Tanggal Pengiriman</span>
                            <strong x-text="current.date"></strong>
                        </div>

                        <div>
                            <span>Nomor Pesanan / Reorder</span>
                            <strong x-text="current.order"></strong>
                        </div>

                        <div>
                            <span>Status Pengiriman</span>
                            <strong
                                x-text="labels[current.status]
                                    ? labels[current.status].text
                                    : current.status"
                            ></strong>
                        </div>
                    </div>

                    <!-- DETAIL BARANG -->
                    <div class="sj-section">
                        <h3>Rincian Barang yang Dikirim</h3>

                        <div class="table-wrapper">
                            <table class="sj-table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Nama Bahan Baku</th>
                                        <th>Jumlah Dikirim</th>
                                        <th x-show="received">Jumlah Diterima</th>
                                        <th x-show="received">Selisih</th>
                                        <th x-show="received">Keterangan</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <template
                                        x-for="(line, index) in current.items"
                                        :key="line.item + '-' + index"
                                    >
                                        <tr>
                                            <td x-text="index + 1"></td>
                                            <td x-text="itemOf(line).name"></td>
                                            <td x-text="sentText(line)"></td>

                                            <td
                                                x-show="received"
                                                x-text="receivedText(line)"
                                            ></td>

                                            <td x-show="received">
                                                <span
                                                    :class="{
                                                        'danger-text':
                                                            Math.abs(
                                                                Number(line.received) -
                                                                Number(line.sent)
                                                            ) > 0.000001
                                                    }"
                                                    x-text="diffText(line)"
                                                ></span>
                                            </td>

                                            <td x-show="received" x-text="line.note || '-'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- CATATAN -->
                    <div class="sj-notes">
                        <strong>Catatan Pengiriman:</strong>
                        <p x-text="current.catatan || 'Tidak ada catatan.'"></p>
                    </div>

                    <!-- TANDA TANGAN -->
                    <div class="sj-signature">
                        <div>
                            <p>Disiapkan dan dikirim oleh,</p>
                            <strong>PIHAK DISTRIBUTION CENTER</strong>
                            <div class="sj-sign-space"></div>
                            <strong x-text="current.prepared_by"></strong>
                            <span>Petugas DC</span>
                        </div>

                        <div>
                            <p>Diterima dan diperiksa oleh,</p>
                            <strong>KEPALA SHIFT / INVENTORY CONTROL</strong>
                            <div class="sj-sign-space"></div>
                            <strong>........................................</strong>
                            <span>Petugas IC Cabang</span>
                        </div>
                    </div>

                    <div class="sj-footer">
                        <p>
                            Dokumen ini diterbitkan secara digital melalui sistem KopSync.
                        </p>
                        <p>
                            Surat Jalan digunakan sebagai bukti pengiriman barang dari
                            Distribution Center ke cabang tujuan.
                        </p>
                    </div>

                </div>
            </div>

        </template>

    </div>

</section>

@endsection

@push('scripts')
<script>
    // Data Surat Jalan berasal dari database.
    function shipmentPage() {

        return {

            items: @json($items),          // barang, kunci = id barang
            stores: @json($storeList),
            shipments: @json($shipments),

            init() {
                const requestedId = Number(new URLSearchParams(window.location.search).get('surat_jalan_id'));
                const shipment = this.shipments.find(item => item.id === requestedId);

                if (shipment) {
                    this.open(shipment);
                }
            },

            view: 'list',          // 'list' | 'detail'
            current: null,
            sending: false,
            cancellingId: null,

            search: '',
            store: '',
            status: '',

            labels: {
                disiapkan: { text: 'Disiapkan', class: 'info' },
                dikirim: { text: 'Dalam Pengiriman', class: 'warning' },
                menunggu: { text: 'Menunggu Pengecekan', class: 'info' },
                diterima: { text: 'Diterima', class: 'success' },
                dibatalkan: { text: 'Dibatalkan', class: 'danger' },
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


            // "10 Botol (7.000 ml)" untuk barang berkemasan, "300 Pcs" untuk barang satuan
            sentText(line) {
                const item = this.itemOf(line);

                if (item.size > 1) {
                    const packageQty = line.sent / item.size;

                    return this.format(packageQty) + ' ' + item.pack
                        + ' (' + this.format(line.sent) + ' ' + item.unit + ')';
                }

                return this.format(line.sent) + ' ' + item.pack;
            },

            receivedText(line) {
                const item = this.itemOf(line);

                if (item.size > 1) {
                    const packageQty = line.received / item.size;

                    return this.format(packageQty) + ' ' + item.pack
                        + ' (' + this.format(line.received) + ' ' + item.unit + ')';
                }

                return this.format(line.received) + ' ' + item.pack;
            },

            diffText(line) {
                const item = this.itemOf(line);
                const value = Number(line.received) - Number(line.sent);

                if (Math.abs(value) < 0.000001) {
                    return '0';
                }

                return (value > 0 ? '+' : '')
                    + this.format(value)
                    + ' '
                    + (item.unit || item.pack);
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
                    && (!this.store || sj.store === this.store)
                    && (!this.status || sj.status === this.status)
                );
            },


            /* ---------- DOKUMEN ---------- */

            // Kolom Diterima, Selisih, dan Catatan hanya tampil jika IC sudah mengonfirmasi
            get received() {

                return !!this.current && this.current.status === 'diterima';
            },


            open(sj) {

                this.current = sj;
                this.view = 'detail';

                window.scrollTo({ top: 0 });
            },


            async dispatchShipment(sj) {
                if (!sj || sj.status !== 'disiapkan' || this.sending) return;

                if (!confirm('Kirim surat jalan ' + sj.no + ' ke cabang tujuan?')) {
                    return;
                }

                this.sending = true;

                try {
                    const token =
                        document.querySelector('meta[name="csrf-token"]')?.content || '';

                    const response = await fetch(`/dc/shipment/${sj.id}/kirim`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                    });

                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        alert(result.message || 'Gagal mengirim surat jalan.');
                        return;
                    }

                    const newStatus = result.status || 'menunggu';

                    sj.status = newStatus;

                    const target = this.shipments.find(item => item.id === sj.id);

                    if (target) {
                        target.status = newStatus;
                    }

                    if (this.current && this.current.id === sj.id) {
                        this.current.status = newStatus;
                    }

                    alert('Surat Jalan berhasil ditandai sebagai dikirim.');
                } catch (error) {
                    alert('Gagal mengirim surat jalan. Periksa koneksi.');
                } finally {
                    this.sending = false;
                }
            },


            async cancelShipment(sj) {
                if (
                    !sj ||
                    sj.status !== 'disiapkan' ||
                    this.cancellingId !== null ||
                    this.sending
                ) {
                    return;
                }

                const confirmed = confirm(
                    'Apakah kamu yakin ingin membatalkan Surat Jalan ' +
                    sj.no +
                    '?\n\nStok DC yang sudah dikurangi akan dikembalikan.'
                );

                if (!confirmed) return;

                const alasan = prompt(
                    'Masukkan alasan pembatalan Surat Jalan ' + sj.no + ':'
                );

                if (alasan === null) return;

                if (!alasan.trim()) {
                    alert('Alasan pembatalan wajib diisi.');
                    return;
                }

                this.cancellingId = sj.id;

                try {
                    const token =
                        document.querySelector('meta[name="csrf-token"]')?.content || '';

                    const response = await fetch(
                        `/dc/shipment/${sj.id}/batalkan`,
                        {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                alasan: alasan.trim(),
                            }),
                        }
                    );

                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const validationMessage = result.errors
                            ? Object.values(result.errors).flat().join('\n')
                            : '';

                        alert(
                            validationMessage ||
                            result.message ||
                            'Gagal membatalkan Surat Jalan.'
                        );

                        return;
                    }

                    // Perbarui status pada daftar.
                    const target = this.shipments.find(
                        item => item.id === sj.id
                    );

                    if (target) {
                        target.status = 'dibatalkan';
                    }

                    // Perbarui status pada objek yang sedang dibuka.
                    if (this.current && this.current.id === sj.id) {
                        this.current.status = 'dibatalkan';
                    }

                    alert(
                        result.message ||
                        'Surat Jalan berhasil dibatalkan dan stok DC telah dikembalikan.'
                    );

                } catch (error) {
                    alert(
                        'Gagal membatalkan Surat Jalan. Periksa koneksi internet.'
                    );
                } finally {
                    this.cancellingId = null;
                }
            },

            back() {

                this.view = 'list';

                window.scrollTo({ top: 0 });
            },
        };
    }
</script>
@endpush