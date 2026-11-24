@extends('layouts.app')

@section('title', 'Dashboard Admin')
@section('description', 'Ringkasan kesiapan organisasi, katalog, dan kondisi stok seluruh lokasi.')

@push('header_custom')
    <a class="admin-btn-primary" href="{{ route('admin.pengguna.create') }}">
        <x-icon name="plus" size="1em" />
        <span>Tambah Pengguna</span>
    </a>
@endpush

@section('content')
@php
    $fmtNum = static function (float|int $value): string {
        $float = (float) $value;
        $decimals = fmod($float, 1.0) !== 0.0 ? 2 : 0;
        $formatted = number_format($float, $decimals, ',', '.');

        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    };

    $branchesNoManager = $actionItems['branches_without_manager'];
    $menusNoVariant = $actionItems['menus_without_variant'];
    $variantsNoRecipe = $actionItems['variants_without_recipe'];
    $hasActionItems = $branchesNoManager->isNotEmpty()
        || $menusNoVariant->isNotEmpty()
        || $variantsNoRecipe->isNotEmpty()
        || $summary['low_stock'] > 0;
@endphp

<section class="page active admin-panel">
    @if ($hasActionItems)
        <div class="admin-priority-card" role="region" aria-label="Perlu perhatian segera">
            <div class="admin-priority-head">
                <div class="admin-priority-title">
                    <x-icon name="triangle-alert" size="1.15em" />
                    <span>Perlu Perhatian Segera</span>
                </div>
                <span class="admin-badge is-warning">Tindakan prioritas</span>
            </div>

            <div class="admin-priority-list">
                @if ($branchesNoManager->isNotEmpty())
                    @php $firstBranch = $branchesNoManager->first(); @endphp
                    <article class="admin-priority-item">
                        <div>
                            <strong>{{ $branchesNoManager->count() }} cabang belum memiliki manajer aktif</strong>
                            <p>Contoh: {{ $firstBranch->nama_cabang }}. Persetujuan restock cabang membutuhkan Manajer Cabang aktif.</p>
                        </div>
                        <a class="admin-priority-link" href="{{ route('admin.pengguna.create', ['role_key' => 'manager', 'cabang_id' => $firstBranch->cabang_id]) }}">
                            <span>Tetapkan Manajer</span>
                            <x-icon name="chevron-right" size="0.95em" />
                        </a>
                    </article>
                @endif

                @if ($menusNoVariant->isNotEmpty())
                    @php $firstMenu = $menusNoVariant->first(); @endphp
                    <article class="admin-priority-item">
                        <div>
                            <strong>{{ $menusNoVariant->count() }} menu belum memiliki varian</strong>
                            <p>Contoh: {{ $firstMenu->nama_menu }}. Tambahkan minimal 1 varian agar menu tampil di kasir POS.</p>
                        </div>
                        <a class="admin-priority-link" href="{{ route('admin.varian-menu.create', ['menu_id' => $firstMenu->menu_id]) }}">
                            <span>Tambah Varian</span>
                            <x-icon name="chevron-right" size="0.95em" />
                        </a>
                    </article>
                @endif

                @if ($variantsNoRecipe->isNotEmpty())
                    @php $firstVariant = $variantsNoRecipe->first(); @endphp
                    <article class="admin-priority-item">
                        <div>
                            <strong>{{ $variantsNoRecipe->count() }} varian belum memiliki resep</strong>
                            <p>Contoh: {{ ($firstVariant->menu?->nama_menu ?? 'Menu').' - '.$firstVariant->nama_varian }}. Resep menentukan potong stok otomatis.</p>
                        </div>
                        <a class="admin-priority-link" href="{{ route('admin.resep-menu.create', ['varian_id' => $firstVariant->varian_id]) }}">
                            <span>Lengkapi Resep</span>
                            <x-icon name="chevron-right" size="0.95em" />
                        </a>
                    </article>
                @endif

                @if ($summary['low_stock'] > 0)
                    <article class="admin-priority-item">
                        <div>
                            <strong>{{ number_format($summary['low_stock'], 0, ',', '.') }} item stok habis atau menipis</strong>
                            <p>{{ number_format($summary['habis_stock'], 0, ',', '.') }} stok habis dan {{ number_format($summary['menipis_stock'], 0, ',', '.') }} di bawah minimum.</p>
                        </div>
                        <a class="admin-priority-link" href="{{ route('admin.stok-cabang.index', ['status' => 'perlu_perhatian']) }}">
                            <span>Tinjau Stok</span>
                            <x-icon name="chevron-right" size="0.95em" />
                        </a>
                    </article>
                @endif
            </div>
        </div>
    @else
        <div class="admin-priority-card is-healthy" role="status">
            <div class="admin-priority-title">
                <x-icon name="circle-check" size="1.15em" />
                <span>Sistem Siap Beroperasi</span>
            </div>
            <p class="admin-field-help">Semua cabang memiliki manajer aktif, seluruh menu memiliki varian serta resep, dan stok aman.</p>
        </div>
    @endif

    <div class="admin-kpi-grid">
        <a class="admin-stat-card" href="{{ route('admin.pengguna.index') }}">
            <div class="admin-stat-icon"><x-icon name="users" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Total Pengguna</span>
                <strong class="admin-stat-value tabular-nums">{{ number_format($summary['users'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub tabular-nums">{{ number_format($summary['active_users'], 0, ',', '.') }} akun aktif</small>
            </div>
        </a>

        <a class="admin-stat-card" href="{{ route('admin.cabang.index') }}">
            <div class="admin-stat-icon"><x-icon name="store" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Total Cabang</span>
                <strong class="admin-stat-value tabular-nums">{{ number_format($summary['cabang'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub">Gerai operasional</small>
            </div>
        </a>

        <a class="admin-stat-card" href="{{ route('admin.dc.index') }}">
            <div class="admin-stat-icon"><x-icon name="warehouse" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Total DC</span>
                <strong class="admin-stat-value tabular-nums">{{ number_format($summary['dc'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub">Distribution center</small>
            </div>
        </a>

        <a class="admin-stat-card" href="{{ route('admin.bahan-baku.index') }}">
            <div class="admin-stat-icon"><x-icon name="package" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Bahan Baku</span>
                <strong class="admin-stat-value tabular-nums">{{ number_format($summary['bahan'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub">Katalog bahan global</small>
            </div>
        </a>

        <a class="admin-stat-card" href="{{ route('admin.menu-produk.index') }}">
            <div class="admin-stat-icon"><x-icon name="coffee" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Menu Produk</span>
                <strong class="admin-stat-value tabular-nums">{{ number_format($summary['menu'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub">Produk siap jual</small>
            </div>
        </a>

        <a class="admin-stat-card {{ $summary['low_stock'] > 0 ? 'is-alert' : '' }}" href="{{ route('admin.stok-cabang.index', ['status' => 'perlu_perhatian']) }}">
            <div class="admin-stat-icon"><x-icon name="triangle-alert" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Stok Perlu Perhatian</span>
                <strong class="admin-stat-value tabular-nums">{{ number_format($summary['low_stock'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub">Habis atau di bawah minimum</small>
            </div>
        </a>
    </div>

    <div class="admin-card">
        <div class="admin-card-head">
            <div>
                <h3>Bahan di bawah stok minimum dan habis</h3>
                <p>Gabungan pemantauan stok Cabang dan Distribution Center, diurutkan dari kondisi paling kritis.</p>
            </div>
            <div class="admin-card-links">
                <a class="admin-btn-outline admin-btn-sm" href="{{ route('admin.stok-cabang.index', ['status' => 'perlu_perhatian']) }}">Stok Cabang</a>
                <a class="admin-btn-outline admin-btn-sm" href="{{ route('admin.stok-dc.index', ['status' => 'perlu_perhatian']) }}">Stok Gudang DC</a>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Lokasi</th>
                        <th scope="col">Bahan Baku</th>
                        <th scope="col" class="is-right">Jumlah Stok</th>
                        <th scope="col" class="is-right">Stok Minimum</th>
                        <th scope="col">Status</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lowStock as $stock)
                        <tr>
                            <td>
                                <span class="admin-location-tag">{{ $stock['lokasi_label'] }}</span>
                                <span>{{ $stock['lokasi'] }}</span>
                            </td>
                            <td>
                                <span class="admin-cell-primary">{{ $stock['bahan'] }}</span>
                                @if ($stock['kategori'] !== '')
                                    <span class="admin-cell-sub">{{ $stock['kategori'] }}</span>
                                @endif
                            </td>
                            <td class="is-right tabular-nums">{{ $fmtNum($stock['jumlah']) }} {{ $stock['satuan'] }}</td>
                            <td class="is-right tabular-nums">{{ $fmtNum($stock['minimum']) }} {{ $stock['satuan'] }}</td>
                            <td>
                                @if ($stock['is_empty'])
                                    <x-admin.status-badge variant="danger" icon="circle-alert">Habis</x-admin.status-badge>
                                @else
                                    <x-admin.status-badge variant="warning" icon="triangle-alert">Menipis</x-admin.status-badge>
                                @endif
                            </td>
                            <td>
                                <a class="admin-btn-outline admin-btn-sm" href="{{ $stock['manage_url'] }}">Atur Batas</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state
                                    icon="circle-check"
                                    title="Semua stok berada di atas batas minimum"
                                    description="Tidak ada bahan baku di cabang maupun gudang DC yang habis atau menipis saat ini."
                                    :actionUrl="route('admin.stok-cabang.index')"
                                    actionLabel="Lihat semua stok cabang"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
