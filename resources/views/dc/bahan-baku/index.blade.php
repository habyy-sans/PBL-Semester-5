@extends('layouts.app')

@section('title', 'Stok Gudang DC')
@section('description', 'Pantau persediaan bahan baku dan catat penambahan stok gudang DC.')

@php
    $bahanData = $bahanList->map(function ($bahan) {
        $stok = $bahan->stokDc->first();

        return [
            'id' => $bahan->bahan_id,
            'nama' => $bahan->nama_bahan,
            'kategori' => $bahan->kategori,
            'satuan' => $bahan->satuan,
            'kemasan' => $bahan->nama_kemasan,
            'isi' => (float) $bahan->isi_per_kemasan,
            'harga' => (float) $bahan->harga_beli,
            'stok' => $stok ? (float) $stok->jumlah_stok : null,
            'minimum' => $stok ? (float) $stok->stok_minimum : null,
        ];
    })->values();
@endphp

@section('content')

<div
    class="bahan-baku-page space-y-6 pb-8"
    x-data="bahanBakuPage(@js($bahanData))"
    x-cloak
>
    {{-- Notifikasi --}}
    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <strong>Berhasil.</strong> {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-semibold">Data belum berhasil diproses.</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    {{-- Header halaman --}}
    <div class="dash-card-header dc-page-header">
        <div class="dash-card-title-group">
        </div>

        <button type="button" @click="openCreate()" class="primary-button">+ Tambah Bahan Baku</button>
    </div>

    {{-- Kartu ringkasan --}}
    <div class="bb-summary-grid">
        <div class="bb-summary-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-stone-500">Jenis Bahan Baku</p>
                    <p class="mt-2 text-3xl font-bold text-stone-800"
                       x-text="items.length"></p>
                    <p class="mt-1 text-xs text-stone-400">Terdaftar di sistem</p>
                </div>
                <span class="stat-icon"><x-icon name="package" /></span>
            </div>
        </div>

        <div class="bb-summary-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-stone-500">Stok Perlu Diperiksa</p>
                    <p class="mt-2 text-3xl font-bold text-orange-700"
                       x-text="items.filter(i => i.stok === null || i.stok <= i.minimum).length"></p>
                    <p class="mt-1 text-xs text-stone-400">
                        Stok kosong atau mencapai batas minimum
                    </p>
                </div>
                <span class="stat-icon"><x-icon name="circle-alert" /></span>
            </div>
        </div>

        <div class="bb-summary-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-stone-500">Stok Dalam Kondisi Aman</p>
                    <p class="mt-2 text-3xl font-bold text-green-700"
                       x-text="items.filter(i => i.stok !== null && i.stok > i.minimum).length"></p>
                    <p class="mt-1 text-xs text-stone-400">
                        Stok di atas batas minimum
                    </p>
                </div>
                <span class="stat-icon"><x-icon name="circle-check" /></span>
            </div>
        </div>
    </div>

    {{-- Tabel dan kontrol --}}
    <div class="bb-list-card">
        <div class="border-b border-stone-100 px-6 py-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-stone-800">Daftar Bahan Baku</h3>
                    <p class="mt-1 text-sm text-stone-500">
                        Cari, filter, dan periksa stok bahan baku DC.
                    </p>
                </div>

                <button
                    type="button"
                    @click="resetFilters()"
                    class="outline-button self-start xl:self-auto"
                >
                    Reset filter
                </button>
            </div>

            <div class="bb-filter-grid mt-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
                <div class="bb-search-filter">
                    <label for="searchBahan" class="mb-1.5 block text-xs font-semibold text-stone-600">
                        Pencarian
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-stone-400"><x-icon name="search" /></span>
                        <input
                            id="searchBahan"
                            type="search"
                            x-model="search"
                            placeholder="Cari ID, nama bahan, atau kategori..."
                            class="bb-input !bg-stone-50 pl-9 pr-3"
                        >
                    </div>
                </div>

                <div>
                    <label for="filterKategori" class="mb-1.5 block text-xs font-semibold text-stone-600">
                        Kategori
                    </label>
                    <select
                        id="filterKategori"
                        x-model="category"
                        class="bb-select !bg-stone-50"
                    >
                        <option value="">Semua kategori</option>
                        <template x-for="kategori in categories" :key="kategori">
                            <option :value="kategori" x-text="kategori"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label for="filterStatus" class="mb-1.5 block text-xs font-semibold text-stone-600">
                        Status stok
                    </label>
                    <select
                        id="filterStatus"
                        x-model="stockStatus"
                        class="bb-select !bg-stone-50"
                    >
                        <option value="">Semua status</option>
                        <option value="aman">Aman</option>
                        <option value="minimum">Perlu restock</option>
                        <option value="kosong">Belum ada stok</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Informasi hasil dan jumlah baris --}}
        <div class="flex flex-col gap-4 border-b border-stone-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-stone-500">
                Menampilkan
                <strong class="text-stone-800" x-text="filteredItems.length ? startIndex + 1 : 0"></strong>
                –
                <strong class="text-stone-800" x-text="Math.min(startIndex + perPage, filteredItems.length)"></strong>
                dari
                <strong class="text-stone-800" x-text="filteredItems.length"></strong>
                bahan
            </p>

            <div class="flex items-center gap-2">
                <label for="sortBahan" class="text-xs text-stone-500">Urutkan:</label>
                <select
                    id="sortBahan"
                    x-model="sortBy"
                    class="bb-select"
                >
                    <option value="id-asc">ID terkecil</option>
                    <option value="id-desc">ID terbesar</option>
                    <option value="nama-asc">Nama A–Z</option>
                    <option value="nama-desc">Nama Z–A</option>
                    <option value="stok-asc">Stok terendah</option>
                    <option value="stok-desc">Stok tertinggi</option>
                    <option value="harga-asc">Harga terendah</option>
                    <option value="harga-desc">Harga tertinggi</option>
                </select>
            </div>
        </div>

        {{-- Tabel responsif --}}
        <div class="bb-table-scroll">
            <table class="bb-table">
                <thead class="bg-amber-50 text-xs uppercase tracking-wide text-stone-600">
                    <tr>
                        <th class="px-5 py-4 font-semibold">No.</th>
                        <th class="px-4 py-4 font-semibold">ID Bahan</th>
                        <th class="px-4 py-4 font-semibold">Nama Bahan</th>
                        <th class="px-4 py-4 font-semibold">Kategori</th>
                        <th class="px-4 py-4 font-semibold">Kemasan</th>
                        <th class="px-4 py-4 font-semibold">Harga Pasok</th>
                        <th class="px-4 py-4 font-semibold">Stok DC</th>
                        <th class="px-4 py-4 font-semibold">Minimum</th>
                        <th class="px-4 py-4 font-semibold">Status</th>
                        <th class="px-4 py-4 font-semibold">Aksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-stone-100">
                    <template x-for="(bahan, index) in paginatedItems" :key="bahan.id">
                        <tr class="transition hover:bg-amber-50/40">
                            <td class="px-5 py-4 text-stone-500"
                                x-text="startIndex + index + 1"></td>

                            <td class="px-4 py-4">
                                <span class="rounded-md bg-stone-100 px-2 py-1 font-mono text-xs font-semibold text-stone-700"
                                      x-text="'BB-' + String(bahan.id).padStart(4, '0')"></span>
                            </td>

                            <td class="px-4 py-4">
                                <p class="font-semibold text-stone-800" x-text="bahan.nama"></p>
                                <p class="mt-1 text-xs text-stone-400"
                                   x-text="'Satuan: ' + bahan.satuan"></p>
                            </td>

                            <td class="px-4 py-4">
                                <span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs text-stone-600"
                                      x-text="bahan.kategori || 'Belum dikategorikan'"></span>
                            </td>

                            <td class="px-4 py-4 text-stone-600">
                                <span x-text="bahan.kemasan"></span>
                                <p class="mt-1 text-xs text-stone-400"
                                   x-text="formatNumber(bahan.isi) + ' ' + bahan.satuan"></p>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 font-medium text-stone-700"
                                x-text="formatRupiah(bahan.harga)"></td>

                            <td class="whitespace-nowrap px-4 py-4 font-semibold text-stone-800">
                                <span x-text="bahan.stok === null ? '—' : formatNumber(bahan.stok)"></span>
                                <span class="text-xs font-normal text-stone-400" x-text="bahan.stok === null ? '' : bahan.satuan"></span>
                                <p class="mt-0.5 text-xs text-stone-400" x-show="bahan.stok !== null && bahan.isi > 0" x-text="'≈ ' + formatNumber(Math.floor(bahan.stok / bahan.isi)) + ' ' + bahan.kemasan"></p>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 text-stone-600">
                                <span x-text="bahan.minimum === null ? '—' : formatNumber(bahan.minimum)"></span>
                                <span class="text-xs text-stone-400" x-text="bahan.minimum === null ? '' : bahan.satuan"></span>
                            </td>

                            <td class="px-4 py-4">
                                <span
                                    class="status whitespace-nowrap"
                                    :class="statusClass(bahan)"
                                    x-text="statusLabel(bahan)"
                                ></span>
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="openEdit(bahan)"
                                        title="Edit informasi bahan baku"
                                        class="bb-action-button bb-action-edit"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        @click="openAddStock(bahan)"
                                        title="Tambah stok masuk gudang DC"
                                        class="bb-action-button bb-action-stock"
                                    >
                                        + Tambah Stok
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filteredItems.length === 0">
                        <td colspan="10" class="px-5 py-14 text-center">
                            <p class="font-semibold text-stone-700">Bahan baku tidak ditemukan</p>
                            <p class="mt-1 text-sm text-stone-400">
                                Coba ubah kata pencarian atau filter.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="flex flex-col gap-4 border-t border-stone-100 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <label for="perPage" class="text-xs text-stone-500">Baris per halaman</label>
                <select
                    id="perPage"
                    x-model.number="perPage"
                    @change="currentPage = 1"
                    class="bb-select"
                >
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>

            <div class="flex items-center justify-between gap-3 sm:justify-end">
                <button
                    type="button"
                    @click="currentPage = Math.max(1, currentPage - 1)"
                    :disabled="currentPage <= 1"
                    class="rounded-lg border border-stone-200 px-3 py-2 text-sm text-stone-700 hover:bg-stone-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Sebelumnya
                </button>

                <span class="whitespace-nowrap text-sm text-stone-500">
                    Halaman <strong x-text="currentPage"></strong> /
                    <strong x-text="totalPages"></strong>
                </span>

                <button
                    type="button"
                    @click="currentPage = Math.min(totalPages, currentPage + 1)"
                    :disabled="currentPage >= totalPages"
                    class="rounded-lg border border-stone-200 px-3 py-2 text-sm text-stone-700 hover:bg-stone-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Berikutnya
                </button>
            </div>
        </div>
    </div>


{{-- Modal tambah bahan baku --}}
<div
    x-show="modal === 'create'"
    x-transition.opacity
    @keydown.escape.window="closeModal()"
    @click.self="closeModal()"
    class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-stone-950/50 p-4 backdrop-blur-sm"
    style="display: none;"
>
    <div x-show="modal === 'create'" x-transition class="bb-modal-panel my-auto">
        <div class="bb-modal-heading">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">
                    Master Bahan Baku
                </p>
                <h3 class="mt-1 text-xl font-bold text-stone-800">
                    Tambah Bahan Baku
                </h3>
                <p class="mt-1 text-sm text-stone-500">
                    Daftarkan bahan baku baru dan stok awal gudang DC.
                </p>
            </div>
            <button type="button" @click="closeModal()"
                class="rounded-lg px-3 py-1 text-2xl text-stone-400 hover:bg-stone-100"
                aria-label="Tutup">×</button>
        </div>

        <form method="POST" action="{{ route('dc.bahan-baku.store') }}">
            @csrf

            <div class="grid grid-cols-2 gap-5">
                <label>
                    <span class="text-sm font-semibold text-stone-700">Nama Bahan *</span>
                    <input type="text" name="nama_bahan" required
                        value="{{ old('nama_bahan') }}"
                        placeholder="Contoh: Fresh Milk"
                        class="bb-input">
                </label>

                <label>
                    <span class="text-sm font-semibold text-stone-700">Kategori *</span>
                    <input type="text" name="kategori" required
                        value="{{ old('kategori') }}"
                        placeholder="Contoh: Dairy"
                        class="bb-input">
                </label>

                <label>
                    <span class="text-sm font-semibold text-stone-700">Satuan Dasar *</span>
                    <input type="text" name="satuan" required
                        placeholder="Contoh: ml, gram, pcs"
                        class="bb-input">
                </label>

                <label>
                    <span class="text-sm font-semibold text-stone-700">Nama Kemasan *</span>
                    <input type="text" name="nama_kemasan" required
                        placeholder="Contoh: Liter, Pack, Botol"
                        class="bb-input">
                </label>

                <label>
                    <span class="text-sm font-semibold text-stone-700">
                        Isi per Kemasan *
                    </span>
                    <input type="number" name="isi_per_kemasan" required
                        min="0.001" step="0.001"
                        placeholder="Contoh: 1000"
                        class="bb-input">
                </label>

                <label>
                    <span class="text-sm font-semibold text-stone-700">
                        Harga Beli per Kemasan *
                    </span>
                    <input type="number" name="harga_beli" required
                        min="0" step="0.01"
                        placeholder="Contoh: 18000"
                        class="bb-input">
                </label>
            </div>

            <div class="rounded-xl border border-amber-100 bg-amber-50/50 p-5">
                <h4 class="font-semibold text-stone-800">Persediaan Awal DC</h4>
                <p class="mt-1 text-xs text-stone-500">
                    Tentukan jumlah stok awal dan batas minimum persediaan.
                    Keduanya menggunakan satuan dasar bahan baku.
                </p>

                <div class="mt-4 grid grid-cols-2 gap-5">
                    <label>
                        <span class="text-sm font-semibold text-stone-700">
                            Stok Awal *
                        </span>
                        <input type="number" name="stok_awal" required
                            min="0" step="0.001" value="0"
                            class="bb-input">
                    </label>

                    <label>
                        <span class="text-sm font-semibold text-stone-700">
                            Stok Minimum *
                        </span>
                        <input type="number" name="stok_minimum" required
                            min="0" step="0.001" value="0"
                            class="bb-input">
                    </label>
                </div>
            </div>

            <div class="bb-modal-footer">
                <button type="button" @click="closeModal()"
                    class="border border-stone-200 text-stone-600">
                    Batal
                </button>
                <button type="submit" class="bb-primary-button">
                    Simpan Bahan Baku
                </button>
            </div>
        </form>
    </div>
</div>

    {{-- Modal edit informasi bahan baku --}}
    <div
        x-show="modal === 'edit'"
        x-transition.opacity
        @keydown.escape.window="closeModal()"
        @click.self="closeModal()"
        class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-stone-950/50 p-4 backdrop-blur-sm"
        style="display: none;"
    >
        <div x-show="modal === 'edit'" x-transition class="bb-modal-panel my-auto">
            <div class="bb-modal-heading">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">
                        Master Bahan Baku
                    </p>
                    <h3 class="mt-1 text-xl font-bold text-stone-800">
                        Edit Informasi Bahan Baku
                    </h3>
                    <p class="mt-1 text-sm text-stone-500">
                        Perubahan informasi tidak mengubah jumlah stok fisik.
                    </p>
                </div>
                <button type="button" @click="closeModal()"
                    class="rounded-lg px-3 py-1 text-2xl text-stone-400 hover:bg-stone-100"
                    aria-label="Tutup">×</button>
            </div>

            <form method="POST" :action="updateUrl">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-2 gap-5">
                    <label>
                        <span class="text-sm font-semibold text-stone-700">Nama Bahan *</span>
                        <input type="text" name="nama_bahan" required
                            x-model="form.nama_bahan" class="bb-input">
                    </label>

                    <label>
                        <span class="text-sm font-semibold text-stone-700">Kategori *</span>
                        <input type="text" name="kategori" required
                            x-model="form.kategori" class="bb-input">
                    </label>

                    <label>
                        <span class="text-sm font-semibold text-stone-700">Satuan Dasar *</span>
                        <input type="text" name="satuan" required
                            x-model="form.satuan" class="bb-input">
                    </label>

                    <label>
                        <span class="text-sm font-semibold text-stone-700">Nama Kemasan *</span>
                        <input type="text" name="nama_kemasan" required
                            x-model="form.nama_kemasan" class="bb-input">
                    </label>

                    <label>
                        <span class="text-sm font-semibold text-stone-700">
                            Isi per Kemasan *
                        </span>
                        <input type="number" name="isi_per_kemasan" required
                            min="0.001" step="0.001"
                            x-model="form.isi_per_kemasan" class="bb-input">
                    </label>

                    <label>
                        <span class="text-sm font-semibold text-stone-700">
                            Harga Beli per Kemasan *
                        </span>
                        <input type="number" name="harga_beli" required
                            min="0" step="0.01"
                            x-model="form.harga_beli" class="bb-input">
                    </label>
                </div>

                <div class="rounded-xl bg-stone-50 p-4 text-sm text-stone-600">
                    <strong>Catatan:</strong> Edit informasi bahan baku tidak mengubah
                    stok DC, stok cabang, ataupun komposisi resep menu yang sudah ada.
                </div>

                <div class="bb-modal-footer">
                    <button type="button" @click="closeModal()"
                        class="border border-stone-200 text-stone-600">
                        Batal
                    </button>
                    <button type="submit" class="bb-primary-button">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal tambah stok --}}
    <div
        x-show="modal === 'stock'"
        x-transition.opacity
        @keydown.escape.window="closeModal()"
        @click.self="closeModal()"
        class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-stone-950/50 p-4 backdrop-blur-sm"
        style="display: none;"
    >
        <div x-show="modal === 'stock'" x-transition class="bb-modal-panel my-auto">
            <div class="flex items-start justify-between border-b border-stone-100 px-6 py-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">Persediaan DC</p>
                    <h3 class="mt-1 text-xl font-bold text-stone-800">Tambah Stok</h3>
                    <p class="mt-1 text-sm text-stone-500">Catat stok masuk untuk bahan yang dipilih.</p>
                </div>
                <button type="button" @click="closeModal()" class="rounded-lg px-3 py-1 text-2xl text-stone-400 hover:bg-stone-100" aria-label="Tutup">×</button>
            </div>

            <form method="POST" :action="stockUrl" class="space-y-5 p-6">
                @csrf

                <div class="rounded-xl bg-stone-50 p-4">
                    <p class="text-xs text-stone-500">Bahan baku</p>
                    <p class="mt-1 font-semibold text-stone-800" x-text="selected ? selected.nama : '—'"></p>
                    <p class="mt-1 font-mono text-xs text-stone-500"
                       x-text="selected ? 'BB-' + String(selected.id).padStart(4, '0') : ''"></p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-stone-500">Stok saat ini</p>
                            <p class="mt-1 font-semibold text-stone-800"
                               x-text="selected && selected.stok !== null ? formatNumber(selected.stok) + ' ' + selected.satuan : 'Belum tersedia'"></p>
                        </div>
                        <div>
                            <p class="text-xs text-stone-500">Stok minimum</p>
                            <p class="mt-1 font-semibold text-stone-800"
                               x-text="selected && selected.minimum !== null ? formatNumber(selected.minimum) + ' ' + selected.satuan : '—'"></p>
                        </div>
                    </div>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-sm font-semibold text-stone-700">
                        Jumlah tambahan (<span x-text="selected ? selected.satuan : 'satuan dasar'"></span>) *
                    </span>
                    <input
                        type="number"
                        name="jumlah"
                        required
                        min="0.001"
                        step="0.001"
                        x-model="stockAmount"
                        placeholder="Masukkan jumlah stok masuk"
                        class="w-full rounded-xl border border-stone-200 px-4 py-3 text-sm outline-none focus:border-amber-700 focus:ring-2 focus:ring-amber-100"
                    >
                    <p class="mt-1.5 text-xs text-stone-400">
                        Masukkan jumlah dalam satuan dasar, misalnya gram atau ml.
                    </p>
                </label>

                <div class="flex flex-col-reverse gap-3 border-t border-stone-100 pt-5 sm:flex-row sm:justify-end">
                    <button type="button" @click="closeModal()" class="rounded-xl border border-stone-200 px-5 py-3 text-sm font-semibold text-stone-600 hover:bg-stone-50">Batal</button>
                    <button type="submit" class="bb-primary-button rounded-xl px-5 py-3 text-sm font-semibold">Simpan Stok Masuk</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection


@push('scripts')
<script>
    function bahanBakuPage(initialItems) {
        return {
            items: initialItems || [],
            search: '',
            category: '',
            stockStatus: '',
            sortBy: 'id-asc',
            currentPage: 1,
            perPage: 10,

            modal: null,
            selected: null,
            updateUrl: '',
            stockUrl: '',
            stockAmount: '',

            form: {
                nama_bahan: '',
                kategori: '',
                satuan: '',
                nama_kemasan: '',
                isi_per_kemasan: '',
                harga_beli: ''
            },

            init() {
                this.$watch('search', () => this.currentPage = 1);
                this.$watch('category', () => this.currentPage = 1);
                this.$watch('stockStatus', () => this.currentPage = 1);
                this.$watch('sortBy', () => this.currentPage = 1);
                this.$watch('perPage', () => this.currentPage = 1);
            },

            get categories() {
                return [...new Set(
                    this.items
                        .map(item => item.kategori)
                        .filter(value => value && value.trim() !== '')
                )].sort((a, b) => a.localeCompare(b, 'id'));
            },

            get filteredItems() {
                const term = this.search.trim().toLocaleLowerCase('id');

                let result = this.items.filter(item => {
                    const matchesSearch = !term || [
                        item.id,
                        'BB-' + String(item.id).padStart(4, '0'),
                        item.nama,
                        item.kategori,
                        item.kemasan,
                        item.satuan
                    ].some(value => String(value ?? '').toLocaleLowerCase('id').includes(term));

                    const matchesCategory =
                        !this.category || item.kategori === this.category;

                    let matchesStatus = true;

                    if (this.stockStatus === 'kosong') {
                        matchesStatus = item.stok === null;
                    } else if (this.stockStatus === 'minimum') {
                        matchesStatus =
                            item.stok !== null &&
                            item.minimum !== null &&
                            item.stok <= item.minimum;
                    } else if (this.stockStatus === 'aman') {
                        matchesStatus =
                            item.stok !== null &&
                            item.minimum !== null &&
                            item.stok > item.minimum;
                    }

                    return matchesSearch && matchesCategory && matchesStatus;
                });

                const [field, direction] = this.sortBy.split('-');
                const multiplier = direction === 'desc' ? -1 : 1;

                result.sort((a, b) => {
                    let comparison = 0;

                    if (field === 'nama') {
                        comparison = a.nama.localeCompare(b.nama, 'id');
                    } else if (field === 'stok') {
                        const aValue = a.stok === null ? -Infinity : a.stok;
                        const bValue = b.stok === null ? -Infinity : b.stok;
                        comparison = aValue - bValue;
                    } else if (field === 'harga') {
                        comparison = a.harga - b.harga;
                    } else {
                        comparison = Number(a.id) - Number(b.id);
                    }

                    return comparison * multiplier;
                });

                return result;
            },

            get totalPages() {
                return Math.max(1, Math.ceil(this.filteredItems.length / this.perPage));
            },

            get startIndex() {
                return (this.currentPage - 1) * this.perPage;
            },

            get paginatedItems() {
                return this.filteredItems.slice(
                    this.startIndex,
                    this.startIndex + Number(this.perPage)
                );
            },

            formatNumber(value) {
                return new Intl.NumberFormat('id-ID', {
                    maximumFractionDigits: 3
                }).format(Number(value));
            },

            formatRupiah(value) {
                return new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2
                }).format(Number(value));
            },

            statusLabel(item) {
                if (item.stok === null) return 'Belum ada stok';
                if (item.minimum !== null && item.stok <= item.minimum) {
                    return 'Perlu restock';
                }
                if (item.minimum === null) return 'Minimum belum ada';
                return 'Aman';
            },

            statusClass(item) {
                if (item.stok === null) {
                    return 'bg-stone-100 text-stone-600';
                }
                if (item.minimum !== null && item.stok <= item.minimum) {
                    return 'bg-orange-100 text-orange-800';
                }
                if (item.minimum === null) {
                    return 'bg-stone-100 text-stone-600';
                }
                return 'bg-green-100 text-green-800';
            },

            openCreate() {
                this.selected = null;
                this.form = {
                    nama_bahan: '',
                    kategori: '',
                    satuan: '',
                    nama_kemasan: '',
                    isi_per_kemasan: '',
                    harga_beli: ''
                };

                this.modal = 'create';
            },

            openEdit(item) {
                this.selected = item;

                this.form = {
                    nama_bahan: item.nama,
                    kategori: item.kategori || '',
                    satuan: item.satuan,
                    nama_kemasan: item.kemasan,
                    isi_per_kemasan: item.isi,
                    harga_beli: item.harga
                };

                this.updateUrl = @js(route('dc.bahan-baku.update', '__ID__'))
                    .replace('__ID__', encodeURIComponent(item.id));

                this.modal = 'edit';
            },

            openAddStock(item) {
                this.selected = item;
                this.stockAmount = '';

                this.stockUrl = @js(route('dc.stok-dc.add-stock', '__ID__'))
                    .replace('__ID__', encodeURIComponent(item.id));

                this.modal = 'stock';
            },

            closeModal() {
                this.modal = null;
                this.selected = null;
                this.stockAmount = '';
            },

            resetFilters() {
                this.search = '';
                this.category = '';
                this.stockStatus = '';
                this.sortBy = 'id-asc';
                this.currentPage = 1;
            }
        };
    }
</script>
@endpush
