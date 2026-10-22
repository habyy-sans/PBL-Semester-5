@extends('layouts.app')

@section('title', $title)
@section('description', 'Monitoring jumlah stok dan batas minimum.')

@section('content')
<section class="page active admin-panel">
    <div class="admin-page-heading">
        <div>
            <h1>{{ $title }}</h1>
            <p>Stok menipis ditandai saat jumlah saat ini kurang dari batas minimum lokasi.</p>
        </div>
        <span class="status {{ $lowCount > 0 ? 'warning' : 'success' }}">{{ number_format($lowCount) }} menipis</span>
    </div>
    @if (session('success'))
        <div class="admin-notice success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="admin-notice error" role="alert">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="page-card admin-data-card">
        <form class="table-toolbar admin-toolbar" method="GET" action="{{ $type === 'cabang' ? route('admin.stok-cabang.index') : route('admin.stok-dc.index') }}">
            <label class="admin-search-field">
                <span class="sr-only">Cari bahan baku</span>
                <input class="search-input" type="search" name="q" value="{{ $keyword }}" placeholder="Cari bahan baku...">
            </label>
            <select class="select admin-filter" name="{{ $type === 'cabang' ? 'cabang_id' : 'dc_id' }}" aria-label="Filter lokasi">
                <option value="">Semua {{ $type === 'cabang' ? 'Cabang' : 'DC' }}</option>
                @foreach ($locations as $location)
                    <option value="{{ $type === 'cabang' ? $location->cabang_id : $location->dc_id }}" @selected(($filters[$type === 'cabang' ? 'cabang_id' : 'dc_id'] ?? '') == ($type === 'cabang' ? $location->cabang_id : $location->dc_id))>
                        {{ $type === 'cabang' ? $location->nama_cabang : $location->nama_dc }}
                    </option>
                @endforeach
            </select>
            <select class="select admin-filter" name="bahan_id" aria-label="Filter bahan baku">
                <option value="">Semua Bahan</option>
                @foreach ($ingredients as $ingredient)
                    <option value="{{ $ingredient->bahan_id }}" @selected(($filters['bahan_id'] ?? '') == $ingredient->bahan_id)>{{ $ingredient->nama_bahan }}</option>
                @endforeach
            </select>
            <select class="select admin-filter" name="status" aria-label="Filter status stok">
                <option value="">Semua Status</option>
                <option value="aman" @selected(($filters['status'] ?? '') === 'aman')>Aman</option>
                <option value="menipis" @selected(($filters['status'] ?? '') === 'menipis')>Menipis</option>
            </select>
            <select class="select admin-filter" name="sort" aria-label="Urutkan stok">
                <option value="">Urutan lokasi</option>
                <option value="jumlah_stok" @selected($sort === 'jumlah_stok')>Jumlah stok</option>
                <option value="stok_minimum" @selected($sort === 'stok_minimum')>Stok minimum</option>
            </select>
            <select class="select admin-filter" name="direction" aria-label="Arah urutan">
                <option value="asc" @selected($direction === 'asc')>Naik</option>
                <option value="desc" @selected($direction === 'desc')>Turun</option>
            </select>
            <button class="outline-button" type="submit">Terapkan</button>
            @if (request()->query())
                <a class="admin-clear-filter" href="{{ $type === 'cabang' ? route('admin.stok-cabang.index') : route('admin.stok-dc.index') }}">Reset</a>
            @endif
            <span class="table-meta">{{ $stocks->total() }} baris</span>
        </form>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead><tr><th>{{ $type === 'cabang' ? 'Cabang' : 'Distribution Center' }}</th><th>Bahan baku</th><th>Jumlah stok</th><th>Stok minimum</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse ($stocks as $stock)
                        @php($low = (float) $stock->jumlah_stok < (float) $stock->stok_minimum)
                        <tr>
                            <td>{{ $type === 'cabang' ? $stock->cabang->nama_cabang : $stock->dc->nama_dc }}</td>
                            <td>{{ $stock->bahan->nama_bahan }} <small class="admin-secondary-line">{{ $stock->bahan->kategori }}</small></td>
                            <td>{{ $stock->jumlah_stok }} {{ $stock->bahan->satuan }}</td>
                            <td>{{ $stock->stok_minimum }} {{ $stock->bahan->satuan }}</td>
                            <td><span class="status {{ $low ? 'warning' : 'success' }}">{{ $low ? 'Menipis' : 'Aman' }}</span></td>
                            <td>
                                <button
                                    class="outline-button admin-small-button"
                                    type="button"
                                    data-minimum-url="{{ $type === 'cabang' ? route('admin.stok-cabang.minimum', $stock) : route('admin.stok-dc.minimum', $stock) }}"
                                    data-minimum="{{ $stock->stok_minimum }}"
                                    data-minimum-item="{{ $stock->bahan->nama_bahan }}"
                                >Atur minimum</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="page-empty">Data stok tidak ditemukan.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="admin-pagination">{{ $stocks->links() }}</div>
    </div>
</section>

<dialog class="admin-confirm-dialog" id="minimum-dialog">
    <form method="POST" id="minimum-form">
        @csrf
        @method('PATCH')
        <h2>Atur stok minimum</h2>
        <p id="minimum-item"></p>
        <label class="admin-modal-label" for="minimum-value">Batas minimum</label>
        <input id="minimum-value" name="stok_minimum" type="number" min="0" step="0.001" required>
        <div class="admin-dialog-actions">
            <button class="outline-button" type="button" data-minimum-close>Batal</button>
            <button class="primary-button" type="submit">Simpan Batas</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
    const minimumDialog = document.getElementById('minimum-dialog');
    document.querySelectorAll('[data-minimum-url]').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('minimum-form').action = button.dataset.minimumUrl;
            document.getElementById('minimum-value').value = button.dataset.minimum;
            document.getElementById('minimum-item').textContent = button.dataset.minimumItem;
            minimumDialog.showModal();
        });
    });
    document.querySelector('[data-minimum-close]')?.addEventListener('click', () => minimumDialog.close());
</script>
@endpush
