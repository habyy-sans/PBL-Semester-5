@extends('layouts.app')

@section('title', 'Histori Mutasi Stok')
@section('description', 'Riwayat stok masuk, stok keluar, dan waste.')

@php
    // DEMO: data dari config/demo.php (nanti diganti data dari database / controller).
    // amount = perubahan stok dalam satuan terkecil (ml / gr / Pcs), + bertambah, - berkurang.
    $items = collect(config('demo.items'))
        ->map(fn ($item, $id) => ['id' => $id] + $item)
        ->keyBy('id')
        ->all();

    $mutations = config('demo.mutations');
@endphp

@section('content')

<section class="page active" x-data="mutationPage()" @keydown.escape.window="detail = null">

    <div class="page-card">

        <div class="table-toolbar">

            <input
                type="search"
                class="search-input"
                placeholder="Cari barang atau menu..."
                aria-label="Cari barang atau menu"
                x-model="search"
            >

            <select class="select" aria-label="Filter jenis" x-model="type">
                <option value="">Semua Jenis</option>
                <option value="masuk">Stok Masuk</option>
                <option value="keluar">Stok Keluar</option>
                <option value="waste">Waste</option>
            </select>

            <label class="filter-label" for="mutation-from">Dari</label>
            <input id="mutation-from" type="date" class="date-input" x-model="from">

            <label class="filter-label" for="mutation-to">Sampai</label>
            <input id="mutation-to" type="date" class="date-input" x-model="to">

            <span class="table-meta" x-text="'Menampilkan ' + filtered.length + ' dari ' + entries.length + ' catatan'"></span>

        </div>

        <div class="table-wrapper">
            <table>

                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Jenis</th>
                        <th>Referensi</th>
                        <th>Barang</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <template x-for="entry in filtered" :key="entry.id">
                        <tr>
                            <td x-text="dateText(entry.date) + ' ' + entry.time"></td>
                            <td x-text="types[entry.type]"></td>
                            <td x-text="entry.ref"></td>
                            <td x-text="entry.lines.length + ' Barang'"></td>
                            <td>
                                <button type="button" class="outline-button" @click="detail = entry">Lihat Detail</button>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filtered.length === 0" x-cloak>
                        <td colspan="5" class="page-empty">Tidak ada mutasi yang cocok dengan pencarian.</td>
                    </tr>

                </tbody>

            </table>
        </div>

        <p class="info-note">
            Penjualan digabung per menu per hari. Rincian tiap transaksi ada di Riwayat Transaksi (Kasir).
        </p>

    </div>


    <!-- =====================================================
         DIALOG DETAIL MUTASI
    ====================================================== -->

    <div class="modal-backdrop" x-show="detail" x-cloak @click.self="detail = null">

        <div class="modal-box wide" role="dialog" aria-modal="true" aria-labelledby="mutation-detail-title">

            <template x-if="detail">

                <div>

                    <h4 id="mutation-detail-title">Detail Mutasi Stok</h4>
                    <p x-text="dateText(detail.date) + ' ' + detail.time"></p>

                    <div class="summary-box">

                        <div class="summary-line">
                            <span>Jenis</span>
                            <span x-text="types[detail.type]"></span>
                        </div>

                        <div class="summary-line">
                            <span>Referensi</span>
                            <span x-text="detail.ref"></span>
                        </div>

                    </div>

                    <div class="summary-box">

                        <p class="summary-title" x-text="'Barang yang berubah (' + detail.lines.length + ')'"></p>

                        <template x-for="line in detail.lines" :key="line.item">
                            <div class="summary-line">
                                <span x-text="itemOf(line).name"></span>
                                <span x-text="amountText(detail.type, line)"></span>
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
    // DEMO: data dari blok @php di atas. Nanti diganti data dari database.
    function mutationPage() {

        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return {

            items: @json($items),          // barang, kunci = id barang
            entries: @json($mutations),

            search: '',
            type: '',
            from: '',
            to: '',

            detail: null,                  // catatan yang sedang dibuka di dialog

            types: {
                masuk:  'Stok Masuk',
                keluar: 'Stok Keluar',
                waste:  'Waste',
            },


            /* ---------- FORMAT ---------- */

            format(value) {

                return new Intl.NumberFormat('id-ID').format(value);
            },


            itemOf(line) {

                return this.items[line.item];
            },


            // '2026-10-14' => '14 Okt 2026'
            dateText(iso) {

                const [year, month, day] = iso.split('-').map(Number);

                return day + ' ' + months[month - 1] + ' ' + year;
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


            // Stok masuk: kemasan utuh, mis. "+10 Botol (7.000 ml)"
            // Stok keluar / waste: satuan terkecil, mis. "-70 ml" atau "-700 ml (1 Botol)"
            amountText(type, line) {

                const item = this.itemOf(line);
                const abs  = Math.abs(line.amount);
                const sign = line.amount > 0 ? '+' : '-';
                const base = this.format(abs) + ' ' + item.unit;

                if (item.size <= 1) {
                    return sign + base;
                }

                if (type === 'masuk') {
                    return sign + this.display(item, abs) + ' (' + base + ')';
                }

                if (abs >= item.size && abs % item.size === 0) {
                    return sign + base + ' (' + this.display(item, abs) + ')';
                }

                return sign + base;
            },


            /* ---------- DAFTAR ---------- */

            // Terbaru di atas
            get sorted() {

                return [...this.entries].sort((a, b) =>
                    (b.date + ' ' + b.time).localeCompare(a.date + ' ' + a.time)
                );
            },


            // Pencarian cocok jika nama menu / referensi cocok,
            // atau ada barang di dalam catatan itu yang namanya cocok.
            get filtered() {

                const keyword = this.search.trim().toLowerCase();

                return this.sorted.filter(entry =>
                    (!this.type || entry.type === this.type)
                    && (!this.from || entry.date >= this.from)
                    && (!this.to || entry.date <= this.to)
                    && (
                        !keyword
                        || entry.ref.toLowerCase().includes(keyword)
                        || entry.lines.some(line => this.itemOf(line).name.toLowerCase().includes(keyword))
                    )
                );
            },

        };
    }
</script>
@endpush