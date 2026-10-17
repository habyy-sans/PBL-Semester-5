@extends('layouts.app')

@section('title', 'inventory')
@section('description', 'Pantau stok setiap store secara real-time.')

@php
    // DEMO: data dari config/demo.php (nanti diganti data dari database / controller).
    // stock dan min dalam satuan terkecil (ml / gr / Pcs), ditampilkan per kemasan.
    $storeList = collect(config('demo.stores'))
        ->map(fn ($store, $id) => ['id' => $id] + $store)
        ->values()
        ->all();

    $rows = [];

    foreach (config('demo.stores') as $storeId => $store) {
        foreach (config('demo.items') as $itemId => $item) {
            $rows[] = [
                'key'       => $storeId . '|' . $itemId,
                'store'     => $storeId,
                'storeName' => $store['name'],
                'stock'     => config("demo.stocks.$storeId.$itemId") ?? 0,
            ] + $item;
        }
    }
@endphp

@section('content')

<section class="page active" x-data="dcStockPage()">

    <div class="page-card">

        <div class="table-toolbar">

            <input
                type="search"
                class="search-input"
                placeholder="Cari nama barang..."
                aria-label="Cari nama barang"
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
                <option value="aman">Aman</option>
                <option value="menipis">Menipis</option>
                <option value="habis">Habis</option>
            </select>

            <span class="table-meta" x-text="'Menampilkan ' + filtered.length + ' dari ' + rows.length + ' data'"></span>

        </div>

        <div class="table-wrapper">
            <table>

                <thead>
                    <tr>
                        <th>Store</th>
                        <th>Nama Barang</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Minimum</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <template x-for="row in filtered" :key="row.key">
                        <tr>
                            <td x-text="row.storeName"></td>
                            <td x-text="row.name"></td>
                            <td x-text="row.category"></td>
                            <td x-text="display(row, row.stock)"></td>
                            <td x-text="display(row, row.min)"></td>
                            <td>
                                <span
                                    class="status"
                                    :class="labels[statusOf(row)].class"
                                    x-text="labels[statusOf(row)].text"
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

        <p class="info-note">
            Halaman ini hanya untuk memantau. Stok store diubah lewat penjualan, penerimaan barang, dan Waste Log di store.
        </p>

    </div>

</section>

@endsection

@push('scripts')
<script>
    // DEMO: data dari blok @php di atas. Nanti diganti data dari database.
    function dcStockPage() {

        return {

            stores: @json($storeList),
            rows: @json($rows),

            search: '',
            store: '',
            status: '',

            labels: {
                aman:    { text: 'Aman',    class: 'success' },
                menipis: { text: 'Menipis', class: 'warning' },
                habis:   { text: 'Habis',   class: 'danger'  },
            },


            // Aturan status: 0 = habis, <= minimum = menipis, selain itu aman
            statusOf(row) {

                if (row.stock <= 0) {
                    return 'habis';
                }

                if (row.stock <= row.min) {
                    return 'menipis';
                }

                return 'aman';
            },


            format(value) {

                return new Intl.NumberFormat('id-ID').format(value);
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


            get filtered() {

                const keyword = this.search.trim().toLowerCase();

                return this.rows.filter(row =>
                    (!keyword || row.name.toLowerCase().includes(keyword))
                    && (!this.store || row.store === this.store)
                    && (!this.status || this.statusOf(row) === this.status)
                );
            },

        };
    }
</script>
@endpush