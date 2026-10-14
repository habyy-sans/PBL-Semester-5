@extends('layouts.app')

@section('title', 'Stok Cabang')
@section('description', 'Pantau stok cabang secara real-time.')

@php
    // DEMO: data dari config/demo.php (nanti diganti data dari database / controller).
    // stock dan min disimpan dalam satuan terkecil (ml / gr / Pcs),
    // lalu ditampilkan per kemasan di halaman ini (contoh: 3430 ml => "4 Botol 630 ml").
    $stockItems = collect(config('demo.items'))
        ->map(fn ($item, $id) => ['id' => $id] + $item)
        ->values()
        ->all();
@endphp

@section('content')

<section class="page active" x-data="stockPage()">

    <!-- RINGKASAN STOK -->
    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">≡</div>
            <div class="stat-content">
                <span class="stat-label">Total Item</span>
                <strong x-text="items.length"></strong>
                <small x-text="categories.length + ' kategori'"></small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">✓</div>
            <div class="stat-content">
                <span class="stat-label">Stok Aman</span>
                <strong x-text="count('aman')"></strong>
                <small class="positive">di atas minimum</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">!</div>
            <div class="stat-content">
                <span class="stat-label">Stok Menipis</span>
                <strong x-text="count('menipis')"></strong>
                <small class="warning">sama atau di bawah minimum</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">×</div>
            <div class="stat-content">
                <span class="stat-label">Stok Habis</span>
                <strong x-text="count('habis')"></strong>
                <small class="danger-text">perlu segera restock</small>
            </div>
        </div>

    </div>


    <!-- TABEL STOK -->
    <div class="page-card section-gap">

        <div class="table-toolbar">

            <input
                type="search"
                class="search-input"
                placeholder="Cari nama barang..."
                aria-label="Cari nama barang"
                x-model="search"
            >

            <select class="select" aria-label="Filter kategori" x-model="category">
                <option value="">Semua Kategori</option>
                <template x-for="name in categories" :key="name">
                    <option :value="name" x-text="name"></option>
                </template>
            </select>

            <select class="select" aria-label="Filter status" x-model="status">
                <option value="">Semua Status</option>
                <option value="aman">Aman</option>
                <option value="menipis">Menipis</option>
                <option value="habis">Habis</option>
            </select>

            <span class="table-meta" x-text="'Menampilkan ' + filtered.length + ' dari ' + items.length + ' item'"></span>

        </div>

        <div class="table-wrapper">
            <table>

                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Isi per Kemasan</th>
                        <th>Stok</th>
                        <th>Minimum</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <template x-for="item in filtered" :key="item.id">
                        <tr>
                            <td x-text="item.name"></td>
                            <td x-text="item.category"></td>
                            <td x-text="packInfo(item)"></td>
                            <td x-text="display(item, item.stock)"></td>
                            <td x-text="display(item, item.min)"></td>
                            <td>
                                <span
                                    class="status"
                                    :class="labels[statusOf(item)].class"
                                    x-text="labels[statusOf(item)].text"
                                ></span>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filtered.length === 0" x-cloak>
                        <td colspan="6" class="page-empty">Tidak ada barang yang cocok dengan pencarian.</td>
                    </tr>

                </tbody>

            </table>
        </div>

    </div>

</section>

@endsection

@push('scripts')
<script>
    // DEMO: data dari $stockItems di atas. Nanti diganti data dari database.
    function stockPage() {

        return {

            items: @json($stockItems),

            search: '',
            category: '',
            status: '',

            labels: {
                aman:    { text: 'Aman',    class: 'success' },
                menipis: { text: 'Menipis', class: 'warning' },
                habis:   { text: 'Habis',   class: 'danger'  },
            },


            // Aturan status: 0 = habis, <= minimum = menipis, selain itu aman
            statusOf(item) {

                if (item.stock <= 0) {
                    return 'habis';
                }

                if (item.stock <= item.min) {
                    return 'menipis';
                }

                return 'aman';
            },


            count(status) {

                return this.items.filter(item => this.statusOf(item) === status).length;
            },


            format(value) {

                return new Intl.NumberFormat('id-ID').format(value);
            },


            // "700 ml" untuk barang berkemasan, "-" untuk barang satuan (Pcs)
            packInfo(item) {

                return item.size > 1 ? this.format(item.size) + ' ' + item.unit : '-';
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


            get categories() {

                return [...new Set(this.items.map(item => item.category))];
            },


            get filtered() {

                const keyword = this.search.trim().toLowerCase();

                return this.items.filter(item =>
                    (!keyword || item.name.toLowerCase().includes(keyword))
                    && (!this.category || item.category === this.category)
                    && (!this.status || this.statusOf(item) === this.status)
                );
            },

        };
    }
</script>
@endpush