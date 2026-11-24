@extends('layouts.app')

@section('title', 'Kelola '.$title)
@section('description', $description)

@push('header_custom')
    <a class="admin-btn-primary" href="{{ route($routeName.'.create', $createQuery) }}">
        <x-icon name="plus" size="1em" />
        <span>Tambah {{ $title }}</span>
    </a>
@endpush

@section('content')
@php
    $fmtNum = static function (float|int $value): string {
        $float = (float) $value;
        $decimals = fmod($float, 1.0) !== 0.0 ? 3 : 0;
        $formatted = number_format($float, $decimals, ',', '.');

        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    };
@endphp

<section class="page active admin-panel">
    <x-admin.flash />

    <div class="admin-card">
        <form class="admin-toolbar" method="GET" action="{{ route($routeName.'.index') }}">
            <div class="admin-search-box">
                <x-icon name="search" size="1em" class="admin-search-icon" />
                <label class="sr-only" for="master-search">Cari {{ $title }}</label>
                <input
                    id="master-search"
                    class="admin-input"
                    type="search"
                    name="q"
                    value="{{ $keyword }}"
                    placeholder="Cari {{ strtolower($title) }}..."
                >
            </div>

            @foreach ($filters as $key => $filter)
                <select class="admin-select" name="{{ $key }}" aria-label="{{ $filter['label'] }}" data-auto-submit>
                    <option value="">{{ $filter['label'] }}: Semua</option>
                    @foreach ($filter['options'] as $option)
                        <option value="{{ $option['value'] }}" @selected((string) ($activeFilters[$key] ?? '') === (string) $option['value'])>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            @endforeach

            @if ($sort)
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
            @endif

            <button class="admin-btn-outline" type="submit">Cari</button>

            @if ($hasActiveFilters)
                <a class="admin-btn-ghost" href="{{ route($routeName.'.index') }}">Reset</a>
            @endif

            <span class="admin-toolbar-meta tabular-nums">{{ number_format($records->total(), 0, ',', '.') }} data</span>
        </form>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            @php
                                $sortCol = $column['sort'] ?? (in_array($column['field'], $sortable, true) ? $column['field'] : null);
                                $isCurrentSort = $sortCol !== null && $sort === $sortCol;
                                $nextDirection = ($isCurrentSort && $direction === 'asc') ? 'desc' : 'asc';
                                $sortUrl = $sortCol !== null
                                    ? request()->fullUrlWithQuery(['sort' => $sortCol, 'direction' => $nextDirection, 'page' => 1])
                                    : null;
                            @endphp
                            <th scope="col" class="{{ ($column['align'] ?? '') === 'right' ? 'is-right' : '' }}">
                                @if ($sortUrl)
                                    <a class="admin-sort-link" href="{{ $sortUrl }}">
                                        <span>{{ $column['label'] }}</span>
                                        <x-icon
                                            :name="$isCurrentSort ? ($direction === 'asc' ? 'arrow-up' : 'arrow-down') : 'arrow-up-down'"
                                            size="0.9em"
                                        />
                                    </a>
                                @else
                                    {{ $column['label'] }}
                                @endif
                            </th>
                        @endforeach
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            @foreach ($columns as $column)
                                @php
                                    $value = isset($column['computed'])
                                        ? $column['computed']($record)
                                        : data_get($record, $column['field']);
                                    $format = $column['format'] ?? null;
                                    $alignRight = ($column['align'] ?? '') === 'right';
                                    $linkUrl = isset($column['linkRoute'], $column['linkParam'])
                                        ? route($column['linkRoute'], [$column['linkParam'] => $record->getKey()])
                                        : null;
                                @endphp
                                <td class="{{ $alignRight ? 'is-right' : '' }} {{ in_array($format, ['currency', 'count', 'unit_amount', 'moq'], true) ? 'tabular-nums' : '' }}">
                                    @if ($format === 'currency')
                                        Rp{{ number_format((float) $value, 0, ',', '.') }}
                                    @elseif ($format === 'package_info')
                                        {{ $fmtNum((float) $value) }} {{ $record->satuan }} / {{ $record->nama_kemasan }}
                                    @elseif ($format === 'moq')
                                        {{ number_format((int) ($value ?? 1), 0, ',', '.') }} {{ $record->nama_kemasan }}
                                    @elseif ($format === 'unit_amount')
                                        {{ $fmtNum((float) $value) }} {{ data_get($record, $column['unitField'] ?? '') }}
                                    @elseif ($format === 'action_link' && $linkUrl)
                                        <a class="admin-relation-link" href="{{ $linkUrl }}">
                                            <span>{{ $column['linkText'] ?? 'Lihat' }}</span>
                                            <x-icon name="chevron-right" size="0.9em" />
                                        </a>
                                    @elseif ($format === 'count')
                                        @php $intCount = (int) $value; @endphp
                                        @if ($intCount === 0 && isset($column['warnZero']))
                                            @if ($linkUrl)
                                                <a href="{{ $linkUrl }}" class="admin-relation-link">
                                                    <x-admin.status-badge variant="warning" icon="triangle-alert">{{ $column['warnZero'] }}</x-admin.status-badge>
                                                </a>
                                            @else
                                                <x-admin.status-badge variant="warning" icon="triangle-alert">{{ $column['warnZero'] }}</x-admin.status-badge>
                                            @endif
                                        @elseif ($linkUrl)
                                            <a class="admin-relation-link" href="{{ $linkUrl }}">{{ number_format($intCount, 0, ',', '.') }}</a>
                                        @else
                                            {{ number_format($intCount, 0, ',', '.') }}
                                        @endif
                                    @else
                                        {{ $value !== null && $value !== '' ? $value : '-' }}
                                    @endif
                                </td>
                            @endforeach

                            <td>
                                <div class="admin-row-actions">
                                    <a class="admin-btn-outline admin-btn-sm" href="{{ route($routeName.'.edit', $record->getKey()) }}">Edit</a>
                                    <button
                                        class="admin-btn-ghost admin-btn-sm is-danger"
                                        type="button"
                                        data-delete-url="{{ route($routeName.'.destroy', $record->getKey()) }}"
                                        data-delete-name="{{ data_get($record, $columns[0]['field']) ?? $title }}"
                                    >Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 1 }}">
                                @if ($hasActiveFilters)
                                    <x-admin.empty-state
                                        icon="search"
                                        title="Data {{ strtolower($title) }} tidak ditemukan"
                                        description="Tidak ada data yang cocok dengan kata kunci atau filter yang dipilih."
                                        :actionUrl="route($routeName.'.index')"
                                        actionLabel="Reset filter"
                                    />
                                @else
                                    <x-admin.empty-state
                                        icon="package"
                                        title="Data {{ strtolower($title) }} belum tersedia"
                                        description="Tambahkan {{ strtolower($title) }} pertama untuk mulai mengelola data."
                                        :actionUrl="route($routeName.'.create', $createQuery)"
                                        actionLabel="Tambah {{ $title }}"
                                    />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $records->links('components.admin.pagination') }}
    </div>
</section>

<x-admin.confirm-dialog
    id="delete-dialog"
    formId="delete-form"
    titleId="delete-title"
    descId="delete-desc"
    defaultTitle="Hapus data?"
    submitLabel="Ya, hapus"
>
    Data <strong id="delete-name"></strong> akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.
</x-admin.confirm-dialog>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => select.form?.submit());
    });

    const deleteDialog = document.getElementById('delete-dialog');
    const deleteForm = document.getElementById('delete-form');
    const deleteName = document.getElementById('delete-name');
    const deleteSubmit = document.getElementById('delete-dialog-submit');

    document.querySelectorAll('[data-delete-url]').forEach((button) => {
        button.addEventListener('click', () => {
            deleteForm.action = button.dataset.deleteUrl;
            deleteName.textContent = button.dataset.deleteName;
            deleteSubmit.disabled = false;
            deleteDialog.showModal();
        });
    });
</script>
@endpush
