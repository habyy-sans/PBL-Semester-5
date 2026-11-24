@extends('layouts.app')

@section('title', $title)
@section('description', $description)

@push('header_custom')
    @if ($lowCount > 0)
        <x-admin.status-badge variant="warning" icon="triangle-alert">
            {{ number_format($counts['habis'], 0, ',', '.') }} habis, {{ number_format($counts['menipis'], 0, ',', '.') }} menipis
        </x-admin.status-badge>
    @else
        <x-admin.status-badge variant="success" icon="circle-check">Semua stok aman</x-admin.status-badge>
    @endif
@endpush

@section('content')
@php
    $fmtNum = static function (float|int $value): string {
        $float = (float) $value;
        $decimals = fmod($float, 1.0) !== 0.0 ? 2 : 0;
        $formatted = number_format($float, $decimals, ',', '.');

        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    };

    $locationKey = $type === 'cabang' ? 'cabang_id' : 'dc_id';
    $activeStatus = $filters['status'] ?? '';
    $baseFilterQuery = array_filter([
        'q' => $keyword,
        $locationKey => $filters[$locationKey] ?? null,
        'bahan_id' => $filters['bahan_id'] ?? null,
        'sort' => $sort,
        'direction' => $sort ? $direction : null,
    ], static fn (mixed $v): bool => $v !== null && $v !== '');
@endphp

<section class="page active admin-panel">
    <x-admin.flash />

    <div class="admin-card">
        <div class="admin-status-pills" role="navigation" aria-label="Filter status stok">
            <a
                class="admin-pill {{ $activeStatus === '' ? 'is-active' : '' }}"
                href="{{ route($indexRoute, $baseFilterQuery) }}"
            >
                <span>Semua</span>
                <span class="admin-pill-count tabular-nums">{{ number_format($counts['all'], 0, ',', '.') }}</span>
            </a>

            <a
                class="admin-pill {{ $activeStatus === 'habis' ? 'is-active' : '' }}"
                href="{{ route($indexRoute, array_merge($baseFilterQuery, ['status' => 'habis'])) }}"
            >
                <span>Habis</span>
                <span class="admin-pill-count tabular-nums">{{ number_format($counts['habis'], 0, ',', '.') }}</span>
            </a>

            <a
                class="admin-pill {{ $activeStatus === 'menipis' ? 'is-active' : '' }}"
                href="{{ route($indexRoute, array_merge($baseFilterQuery, ['status' => 'menipis'])) }}"
            >
                <span>Menipis</span>
                <span class="admin-pill-count tabular-nums">{{ number_format($counts['menipis'], 0, ',', '.') }}</span>
            </a>

            <a
                class="admin-pill {{ $activeStatus === 'aman' ? 'is-active' : '' }}"
                href="{{ route($indexRoute, array_merge($baseFilterQuery, ['status' => 'aman'])) }}"
            >
                <span>Aman</span>
                <span class="admin-pill-count tabular-nums">{{ number_format($counts['aman'], 0, ',', '.') }}</span>
            </a>
        </div>

        <form class="admin-toolbar" method="GET" action="{{ route($indexRoute) }}">
            @if ($activeStatus !== '')
                <input type="hidden" name="status" value="{{ $activeStatus }}">
            @endif
            @if ($sort)
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
            @endif

            <div class="admin-search-box">
                <x-icon name="search" size="1em" class="admin-search-icon" />
                <label class="sr-only" for="stock-search">Cari bahan baku</label>
                <input
                    id="stock-search"
                    class="admin-input"
                    type="search"
                    name="q"
                    value="{{ $keyword }}"
                    placeholder="Cari nama bahan baku..."
                >
            </div>

            <select class="admin-select" name="{{ $locationKey }}" aria-label="Filter lokasi" data-auto-submit>
                <option value="">Semua {{ $type === 'cabang' ? 'Cabang' : 'DC' }}</option>
                @foreach ($locations as $location)
                    @php $locId = $type === 'cabang' ? $location->cabang_id : $location->dc_id; @endphp
                    <option value="{{ $locId }}" @selected((string) ($filters[$locationKey] ?? '') === (string) $locId)>
                        {{ $type === 'cabang' ? $location->nama_cabang : $location->nama_dc }}
                    </option>
                @endforeach
            </select>

            <select class="admin-select" name="bahan_id" aria-label="Filter bahan baku" data-auto-submit>
                <option value="">Semua Bahan Baku</option>
                @foreach ($ingredients as $ingredient)
                    <option value="{{ $ingredient->bahan_id }}" @selected((string) ($filters['bahan_id'] ?? '') === (string) $ingredient->bahan_id)>
                        {{ $ingredient->nama_bahan }}
                    </option>
                @endforeach
            </select>

            <button class="admin-btn-outline" type="submit">Cari</button>

            @if ($hasActiveFilters)
                <a class="admin-btn-ghost" href="{{ route($indexRoute) }}">Reset</a>
            @endif

            <span class="admin-toolbar-meta tabular-nums">{{ number_format($stocks->total(), 0, ',', '.') }} baris</span>
        </form>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">{{ $type === 'cabang' ? 'Cabang' : 'Distribution Center' }}</th>
                        <th scope="col">Bahan Baku</th>
                        <th scope="col" class="is-right">
                            @php $nextQtyDir = ($sort === 'jumlah_stok' && $direction === 'asc') ? 'desc' : 'asc'; @endphp
                            <a class="admin-sort-link" href="{{ request()->fullUrlWithQuery(['sort' => 'jumlah_stok', 'direction' => $nextQtyDir, 'page' => 1]) }}">
                                <span>Jumlah Stok</span>
                                <x-icon :name="$sort === 'jumlah_stok' ? ($direction === 'asc' ? 'arrow-up' : 'arrow-down') : 'arrow-up-down'" size="0.9em" />
                            </a>
                        </th>
                        <th scope="col" class="is-right">
                            @php $nextMinDir = ($sort === 'stok_minimum' && $direction === 'asc') ? 'desc' : 'asc'; @endphp
                            <a class="admin-sort-link" href="{{ request()->fullUrlWithQuery(['sort' => 'stok_minimum', 'direction' => $nextMinDir, 'page' => 1]) }}">
                                <span>Stok Minimum</span>
                                <x-icon :name="$sort === 'stok_minimum' ? ($direction === 'asc' ? 'arrow-up' : 'arrow-down') : 'arrow-up-down'" size="0.9em" />
                            </a>
                        </th>
                        <th scope="col">Status</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stock)
                        @php
                            $qty = (float) $stock->jumlah_stok;
                            $min = (float) $stock->stok_minimum;
                            $empty = $qty <= 0;
                            $low = ! $empty && $qty < $min;
                            $locName = $type === 'cabang' ? ($stock->cabang?->nama_cabang ?? '-') : ($stock->dc?->nama_dc ?? '-');
                        @endphp
                        <tr>
                            <td>{{ $locName }}</td>
                            <td>
                                <span class="admin-cell-primary">{{ $stock->bahan?->nama_bahan ?? '-' }}</span>
                                <span class="admin-cell-sub">{{ $stock->bahan?->kategori ?? '-' }}</span>
                            </td>
                            <td class="is-right tabular-nums">{{ $fmtNum($qty) }} {{ $stock->bahan?->satuan }}</td>
                            <td class="is-right tabular-nums">{{ $fmtNum($min) }} {{ $stock->bahan?->satuan }}</td>
                            <td>
                                @if ($empty)
                                    <x-admin.status-badge variant="danger" icon="circle-alert">Habis</x-admin.status-badge>
                                @elseif ($low)
                                    <x-admin.status-badge variant="warning" icon="triangle-alert">Menipis</x-admin.status-badge>
                                @else
                                    <x-admin.status-badge variant="success" icon="circle-check">Aman</x-admin.status-badge>
                                @endif
                            </td>
                            <td>
                                <button
                                    class="admin-btn-outline admin-btn-sm"
                                    type="button"
                                    data-minimum-url="{{ $type === 'cabang' ? route('admin.stok-cabang.minimum', $stock) : route('admin.stok-dc.minimum', $stock) }}"
                                    data-minimum="{{ $stock->stok_minimum }}"
                                    data-current="{{ $stock->jumlah_stok }}"
                                    data-unit="{{ $stock->bahan?->satuan }}"
                                    data-location="{{ $locName }}"
                                    data-item="{{ $stock->bahan?->nama_bahan }}"
                                >Atur minimum</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                @if ($hasActiveFilters)
                                    <x-admin.empty-state
                                        icon="search"
                                        title="Data stok tidak ditemukan"
                                        description="Ubah filter status, lokasi, atau nama bahan untuk melihat data stok lainnya."
                                        :actionUrl="route($indexRoute)"
                                        actionLabel="Reset filter"
                                    />
                                @else
                                    <x-admin.empty-state
                                        icon="boxes"
                                        title="Belum ada baris stok tercatat"
                                        description="Baris stok akan dibuat otomatis saat cabang, DC, atau bahan baku ditambahkan."
                                    />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $stocks->links('components.admin.pagination') }}
    </div>
</section>

<dialog class="admin-confirm-dialog" id="minimum-dialog" aria-labelledby="minimum-title" aria-describedby="minimum-desc">
    <form method="POST" id="minimum-form">
        @csrf
        @method('PATCH')

        <h3 id="minimum-title">Atur batas stok minimum</h3>
        <p id="minimum-desc">
            <strong id="minimum-item"></strong> di <span id="minimum-location"></span>.
            Stok saat ini: <strong class="tabular-nums" id="minimum-current"></strong>.
        </p>

        <div class="admin-dialog-field">
            <label for="minimum-value">Batas minimum (<span id="minimum-unit-label"></span>)</label>
            <input id="minimum-value" name="stok_minimum" type="number" min="0" max="999999999" step="any" required>
        </div>

        <div class="admin-dialog-preview">
            <span>Pratinjau status dengan batas baru:</span>
            <strong id="minimum-preview-status">-</strong>
        </div>

        <div class="admin-dialog-actions">
            <button class="admin-btn-outline" type="button" data-dialog-close="minimum-dialog">Batal</button>
            <button class="admin-btn-primary" type="submit" id="minimum-submit">Simpan Batas</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => select.form?.submit());
    });

    const minimumDialog = document.getElementById('minimum-dialog');
    const minimumForm = document.getElementById('minimum-form');
    const minimumValue = document.getElementById('minimum-value');
    const minimumItem = document.getElementById('minimum-item');
    const minimumLocation = document.getElementById('minimum-location');
    const minimumCurrent = document.getElementById('minimum-current');
    const minimumUnitLabel = document.getElementById('minimum-unit-label');
    const minimumPreview = document.getElementById('minimum-preview-status');
    const minimumSubmit = document.getElementById('minimum-submit');

    let currentQty = 0;

    const updateStatusPreview = () => {
        const minVal = parseFloat(minimumValue.value);
        if (currentQty <= 0) {
            minimumPreview.textContent = 'Habis';
            return;
        }
        if (Number.isFinite(minVal) && currentQty < minVal) {
            minimumPreview.textContent = 'Menipis (alert restock aktif)';
            return;
        }
        minimumPreview.textContent = 'Aman';
    };

    document.querySelectorAll('[data-minimum-url]').forEach((button) => {
        button.addEventListener('click', () => {
            currentQty = parseFloat(button.dataset.current || '0');
            const unit = button.dataset.unit || '';
            minimumForm.action = button.dataset.minimumUrl;
            minimumValue.value = button.dataset.minimum;
            minimumItem.textContent = button.dataset.item;
            minimumLocation.textContent = button.dataset.location;
            minimumCurrent.textContent = `${currentQty} ${unit}`.trim();
            minimumUnitLabel.textContent = unit || 'unit';
            minimumSubmit.disabled = false;
            updateStatusPreview();
            minimumDialog.showModal();
        });
    });

    minimumValue?.addEventListener('input', updateStatusPreview);
</script>
@endpush
