
@extends('layouts.app')

@section('title', 'Monitoring Stok Cabang')
@section('description', 'Pantau stok bahan baku di setiap cabang.')

@section('content')

<section class="page active" x-data="dcStockPage()">

    {{-- FILTER MONITORING --}}
    <div class="page-card">

        <div class="table-toolbar">

            <input
                type="search"
                class="search-input"
                placeholder="Cari nama bahan..."
                aria-label="Cari nama bahan"
                x-model="search"
            >

            <select
                class="select"
                aria-label="Filter bahan baku"
                x-model="bahan"
            >
                <option value="">Semua Bahan Baku</option>

                <template x-for="item in bahanOptions" :key="item.id">
                    <option
                        :value="item.id"
                        x-text="item.name"
                    ></option>
                </template>
            </select>

            <select
                class="select"
                aria-label="Filter status stok"
                x-model="status"
            >
                <option value="">Semua Status</option>
                <option value="aman">Aman</option>
                <option value="menipis">Menipis</option>
                <option value="habis">Habis</option>
            </select>

        </div>

        <p class="info-note">
            Filter berlaku untuk seluruh tabel cabang di bawah ini.
        </p>

    </div>


    {{-- TABEL PER CABANG --}}
    <template x-for="branch in branches" :key="branch.id">

        <div class="page-card dc-branch-card">

            <div class="dc-branch-heading">
                <div>
                    <h2 x-text="branch.name"></h2>

                    <p>
                        Monitoring persediaan bahan baku cabang
                    </p>
                </div>

                <span
                    class="dc-branch-total"
                    x-text="filteredRows(branch).length + ' dari ' + branch.rows.length + ' bahan'"
                ></span>
            </div>


            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Nama Bahan Baku</th>
                            <th>Kategori</th>
                            <th>Stok Tersedia</th>
                            <th>Stok Minimum</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <template
                            x-for="row in filteredRows(branch)"
                            :key="row.key"
                        >
                            <tr>

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

                        <tr
                            x-show="filteredRows(branch).length === 0"
                            x-cloak
                        >
                            <td colspan="5" class="page-empty">
                                Tidak ada bahan baku yang sesuai dengan filter.
                            </td>
                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </template>


    <p class="info-note">
        Halaman ini hanya digunakan untuk monitoring. Perubahan stok cabang
        dilakukan melalui transaksi penjualan, penerimaan barang, dan Waste Log
        di masing-masing cabang.
    </p>

</section>

@endsection


@push('scripts')
<script>
    function dcStockPage() {

        return {

            branches: @json($branches),
            bahanOptions: @json($bahanList),

            search: '',
            bahan: '',
            status: '',

            labels: {
                aman: {
                    text: 'Aman',
                    class: 'success'
                },

                menipis: {
                    text: 'Menipis',
                    class: 'warning'
                },

                habis: {
                    text: 'Habis',
                    class: 'danger'
                },
            },


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

                return new Intl.NumberFormat('id-ID', {
                    maximumFractionDigits: 2
                }).format(value);
            },


            display(item, amount) {

                if (amount <= 0) {
                    return '0 ' + item.pack;
                }

                if (item.size <= 1) {
                    return this.format(amount) + ' ' + item.pack;
                }

                const packs = Math.floor(amount / item.size);

                const rest = Math.round(
                    (amount - packs * item.size) * 100
                ) / 100;

                if (packs === 0) {
                    return this.format(rest) + ' ' + item.unit;
                }

                if (rest === 0) {
                    return this.format(packs) + ' ' + item.pack;
                }

                return this.format(packs)
                    + ' ' + item.pack
                    + ' ' + this.format(rest)
                    + ' ' + item.unit;
            },


            filteredRows(branch) {

                const keyword = this.search.trim().toLowerCase();

                return branch.rows.filter(row => {

                    const matchSearch =
                        !keyword ||
                        row.name.toLowerCase().includes(keyword);

                    const matchBahan =
                        !this.bahan ||
                        row.bahanId === this.bahan;

                    const matchStatus =
                        !this.status ||
                        this.statusOf(row) === this.status;

                    return matchSearch && matchBahan && matchStatus;
                });
            },

        };
    }
</script>
@endpush
