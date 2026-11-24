@extends('layouts.app')

@section('title', 'Kelola Resep Menu')
@section('description', 'Kelola komposisi multi-bahan baku (Takaran SOP) per varian menu beserta estimasi HPP dan margin.')

@push('header_custom')
    <a class="admin-btn-primary" href="{{ route('admin.resep-menu.create') }}">
        <x-icon name="plus" size="1em" />
        <span>Susun Resep</span>
    </a>
@endpush

@section('content')
@php
    $fmtQty = static function (float|int|string $value): string {
        $float = (float) $value;
        $decimals = fmod($float, 1.0) !== 0.0 ? 3 : 0;
        $formatted = number_format($float, $decimals, ',', '.');

        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    };

    $activeStatus = $filters['status'] ?? '';
    $baseFilterQuery = array_filter([
        'q' => $keyword,
        'kategori_menu_id' => $filters['kategori_menu_id'] ?? null,
        'menu_id' => $filters['menu_id'] ?? null,
        'bahan_id' => $filters['bahan_id'] ?? null,
    ], static fn (mixed $v): bool => $v !== null && $v !== '');
@endphp

<section class="page active admin-panel">
    <x-admin.flash />

    <div class="admin-card">
        <div class="admin-status-pills" role="navigation" aria-label="Filter status resep">
            <a
                class="admin-pill {{ $activeStatus === '' ? 'is-active' : '' }}"
                href="{{ route('admin.resep-menu.index', $baseFilterQuery) }}"
            >
                <span>Semua Varian</span>
                <span class="admin-pill-count tabular-nums">{{ number_format($counts['all'], 0, ',', '.') }}</span>
            </a>

            <a
                class="admin-pill {{ $activeStatus === 'lengkap' ? 'is-active' : '' }}"
                href="{{ route('admin.resep-menu.index', array_merge($baseFilterQuery, ['status' => 'lengkap'])) }}"
            >
                <span>Sudah Ada Resep</span>
                <span class="admin-pill-count tabular-nums">{{ number_format($counts['lengkap'], 0, ',', '.') }}</span>
            </a>

            <a
                class="admin-pill {{ $activeStatus === 'belum' ? 'is-active' : '' }}"
                href="{{ route('admin.resep-menu.index', array_merge($baseFilterQuery, ['status' => 'belum'])) }}"
            >
                <span>Belum Ada Resep</span>
                <span class="admin-pill-count tabular-nums">{{ number_format($counts['belum'], 0, ',', '.') }}</span>
            </a>
        </div>

        <form class="admin-toolbar" method="GET" action="{{ route('admin.resep-menu.index') }}">
            @if ($activeStatus !== '')
                <input type="hidden" name="status" value="{{ $activeStatus }}">
            @endif

            <div class="admin-search-box">
                <x-icon name="search" size="1em" class="admin-search-icon" />
                <label class="sr-only" for="resep-search">Cari menu, varian, atau bahan</label>
                <input
                    id="resep-search"
                    class="admin-input"
                    type="search"
                    name="q"
                    value="{{ $keyword }}"
                    placeholder="Cari nama menu, varian, atau bahan baku..."
                >
            </div>

            <select class="admin-select" name="kategori_menu_id" aria-label="Filter kategori menu" data-auto-submit>
                <option value="">Semua Kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->kategori_menu_id }}" @selected(($filters['kategori_menu_id'] ?? '') === (string) $category->kategori_menu_id)>
                        {{ $category->nama_kategori }}
                    </option>
                @endforeach
            </select>

            <select class="admin-select" name="menu_id" aria-label="Filter menu produk" data-auto-submit>
                <option value="">Semua Menu Produk</option>
                @foreach ($menus as $menu)
                    <option value="{{ $menu->menu_id }}" @selected(($filters['menu_id'] ?? '') === (string) $menu->menu_id)>
                        {{ $menu->nama_menu }}
                    </option>
                @endforeach
            </select>

            <select class="admin-select" name="bahan_id" aria-label="Filter bahan penyusun" data-auto-submit>
                <option value="">Semua Bahan Baku</option>
                @foreach ($ingredients as $ingredient)
                    <option value="{{ $ingredient->bahan_id }}" @selected(($filters['bahan_id'] ?? '') === (string) $ingredient->bahan_id)>
                        {{ $ingredient->nama_bahan }}
                    </option>
                @endforeach
            </select>

            <button class="admin-btn-outline" type="submit">Cari</button>

            @if ($hasActiveFilters)
                <a class="admin-btn-ghost" href="{{ route('admin.resep-menu.index') }}">Reset</a>
            @endif

            <span class="admin-toolbar-meta tabular-nums">{{ number_format($variants->total(), 0, ',', '.') }} varian</span>
        </form>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Menu & Varian</th>
                        <th scope="col">Komposisi Bahan (Takaran SOP)</th>
                        <th scope="col" class="is-right">Est. HPP / Porsi</th>
                        <th scope="col" class="is-right">Harga Jual & Margin</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($variants as $variant)
                        @php
                            $m = $metrics[$variant->varian_id] ?? ['selling_price' => 0, 'hpp' => 0, 'profit' => 0, 'margin' => 0];
                            $hasRecipes = $variant->resep->isNotEmpty();
                            $variantLabel = ($variant->menu?->nama_menu ?? 'Menu').' - '.$variant->nama_varian;
                        @endphp
                        <tr>
                            <td>
                                <span class="admin-cell-primary">{{ $variantLabel }}</span>
                                <span class="admin-cell-sub">{{ $variant->menu?->kategori?->nama_kategori ?? 'Tanpa kategori' }}</span>
                            </td>
                            <td>
                                @if ($hasRecipes)
                                    <div class="admin-recipe-chips">
                                        @foreach ($variant->resep as $resep)
                                            <span class="admin-recipe-chip">
                                                <span>{{ $resep->bahan?->nama_bahan ?? '-' }}</span>
                                                <strong class="tabular-nums">{{ $fmtQty($resep->jumlah_bahan) }} {{ $resep->bahan?->satuan }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <x-admin.status-badge variant="warning" icon="triangle-alert">Belum ada resep</x-admin.status-badge>
                                @endif
                            </td>
                            <td class="is-right tabular-nums">
                                @if ($hasRecipes)
                                    Rp{{ number_format($m['hpp'], 0, ',', '.') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="is-right tabular-nums">
                                <span class="admin-cell-primary">Rp{{ number_format($m['selling_price'], 0, ',', '.') }}</span>
                                @if ($hasRecipes)
                                    <span class="admin-cell-sub {{ $m['profit'] < 0 ? 'is-danger-text' : '' }}">
                                        Margin {{ number_format($m['margin'], 0, ',', '.') }}%
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="admin-row-actions">
                                    <a class="admin-btn-outline admin-btn-sm" href="{{ route('admin.resep-menu.builder', $variant) }}">
                                        Atur Resep
                                    </a>
                                    @if ($hasRecipes)
                                        <button
                                            class="admin-btn-ghost admin-btn-sm is-danger"
                                            type="button"
                                            data-clear-url="{{ route('admin.resep-menu.clear', $variant) }}"
                                            data-clear-name="{{ $variantLabel }}"
                                        >Kosongkan</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                @if ($hasActiveFilters)
                                    <x-admin.empty-state
                                        icon="search"
                                        title="Resep menu tidak ditemukan"
                                        description="Ubah kata kunci pencarian atau filter untuk melihat varian menu lainnya."
                                        :actionUrl="route('admin.resep-menu.index')"
                                        actionLabel="Reset filter"
                                    />
                                @else
                                    <x-admin.empty-state
                                        icon="clipboard-list"
                                        title="Belum ada varian menu"
                                        description="Tambahkan Menu Produk terlebih dahulu agar dapat disusun resep bahan bakunya."
                                        :actionUrl="route('admin.menu-produk.create')"
                                        actionLabel="Tambah Menu Produk"
                                    />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $variants->links('components.admin.pagination') }}
    </div>
</section>

<x-admin.confirm-dialog
    id="clear-recipe-dialog"
    formId="clear-recipe-form"
    titleId="clear-recipe-title"
    descId="clear-recipe-description"
    defaultTitle="Kosongkan resep varian?"
    submitLabel="Ya, kosongkan"
>
    Seluruh komposisi bahan baku untuk varian ini akan dihapus.
</x-admin.confirm-dialog>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => select.form?.submit());
    });

    const clearDialog = document.getElementById('clear-recipe-dialog');
    const clearForm = document.getElementById('clear-recipe-form');
    const clearTitle = document.getElementById('clear-recipe-title');
    const clearDescription = document.getElementById('clear-recipe-description');
    const clearSubmit = document.getElementById('clear-recipe-dialog-submit');

    document.querySelectorAll('[data-clear-url]').forEach((button) => {
        button.addEventListener('click', () => {
            clearForm.action = button.dataset.clearUrl;
            clearTitle.textContent = `Kosongkan resep "${button.dataset.clearName}"?`;
            clearDescription.textContent = `Semua bahan baku pada "${button.dataset.clearName}" akan dihapus dari takaran SOP.`;
            clearSubmit.disabled = false;
            clearDialog.showModal();
        });
    });
</script>
@endpush
