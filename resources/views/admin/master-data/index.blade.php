@extends('layouts.app')

@section('title', 'Kelola '.$title)
@section('description', 'Cari, filter, dan perbarui data '.$title.'.')

@section('content')
<section class="page active admin-panel">
    <div class="admin-page-heading">
        <div>
            <h1>{{ $title }}</h1>
            <p>Kelola data master untuk mendukung operasional lintas cabang.</p>
        </div>
        <a class="primary-button" href="{{ route($routeName.'.create') }}">+ Tambah {{ $title }}</a>
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
        <form class="table-toolbar admin-toolbar" method="GET" action="{{ route($routeName.'.index') }}">
            <label class="admin-search-field">
                <span class="sr-only">Cari {{ $title }}</span>
                <input class="search-input" type="search" name="q" value="{{ $keyword }}" placeholder="Cari {{ strtolower($title) }}...">
            </label>
            @foreach ($filters as $key => $filter)
                <select class="select admin-filter" name="{{ $key }}" aria-label="{{ $filter['label'] }}">
                    <option value="">{{ $filter['label'] }}: Semua</option>
                    @foreach ($filter['options'] as $option)
                        <option value="{{ $option['value'] }}" @selected(request($key) == $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            @endforeach
            <select class="select admin-filter" name="sort" aria-label="Urutkan berdasarkan">
                <option value="">Urutkan: Default</option>
                @foreach ($sortable as $field)
                    <option value="{{ $field }}" @selected($sort === $field)>{{ str($field)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
            <select class="select admin-filter" name="direction" aria-label="Arah urutan">
                <option value="asc" @selected($direction === 'asc')>A–Z / Kecil–Besar</option>
                <option value="desc" @selected($direction === 'desc')>Z–A / Besar–Kecil</option>
            </select>
            <button type="submit" class="outline-button">Terapkan</button>
            @if (request()->query())
                <a class="admin-clear-filter" href="{{ route($routeName.'.index') }}">Reset</a>
            @endif
            <span class="table-meta">{{ $records->total() }} data</span>
        </form>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead><tr>
                    @foreach ($columns as $column)<th scope="col">{{ $column['label'] }}</th>@endforeach
                    <th scope="col">Aksi</th>
                </tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            @foreach ($columns as $column)
                                @php($value = data_get($record, $column['field']))
                                <td>
                                    @if (($column['format'] ?? null) === 'currency')
                                        Rp{{ number_format((float) $value, 0, ',', '.') }}
                                    @elseif (($column['field'] ?? null) === 'pengguna_count' || ($column['field'] ?? null) === 'menu_produk_count' || ($column['field'] ?? null) === 'varian_count' || ($column['field'] ?? null) === 'resep_count')
                                        {{ number_format((int) $value) }}
                                    @else
                                        {{ $value ?? '—' }}
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <div class="admin-row-actions">
                                    <a class="outline-button admin-small-button" href="{{ route($routeName.'.edit', $record->getKey()) }}">Edit</a>
                                    <button
                                        class="text-button danger-text"
                                        type="button"
                                        data-delete-url="{{ route($routeName.'.destroy', $record->getKey()) }}"
                                        data-delete-name="{{ data_get($record, $columns[0]['field']) }}"
                                    >Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($columns) + 1 }}"><div class="page-empty">Data {{ strtolower($title) }} belum tersedia.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="admin-pagination">{{ $records->links() }}</div>
    </div>
</section>

<dialog class="admin-confirm-dialog" id="delete-dialog">
    <form method="POST" id="delete-form">
        @csrf
        @method('DELETE')
        <h2>Hapus data?</h2>
        <p>Data <strong id="delete-name"></strong> akan dihapus. Tindakan ini tidak dapat dibatalkan.</p>
        <div class="admin-dialog-actions">
            <button class="outline-button" type="button" data-dialog-close>Batal</button>
            <button class="primary-button admin-danger-button" type="submit">Ya, hapus</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
    const deleteDialog = document.getElementById('delete-dialog');
    document.querySelectorAll('[data-delete-url]').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('delete-form').action = button.dataset.deleteUrl;
            document.getElementById('delete-name').textContent = button.dataset.deleteName;
            deleteDialog.showModal();
        });
    });
    document.querySelector('[data-dialog-close]')?.addEventListener('click', () => deleteDialog.close());
</script>
@endpush
