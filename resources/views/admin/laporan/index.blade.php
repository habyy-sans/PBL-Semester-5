@extends('layouts.app')

@section('title', 'Laporan Operasional Cabang')
@section('description', 'Ringkasan penjualan, HPP, kerugian waste, dan mutasi stok seluruh cabang dari data transaksi aktual.')

@push('header_custom')
    <a
        class="admin-btn-outline"
        href="{{ route('admin.report', array_filter(['cabang_id' => $selectedCabangId, 'from' => $from, 'to' => $to, 'export' => 1])) }}"
    >
        <x-icon name="download" size="1em" />
        <span>Unduh CSV</span>
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
    $isFiltered = $selectedCabangId !== null
        || $from !== now()->startOfMonth()->toDateString()
        || $to !== now()->toDateString();
@endphp

<section class="page active admin-panel">
    <x-admin.flash />

    <div class="admin-card">
        <form class="admin-toolbar" method="GET" action="{{ route('admin.report') }}">
            <select class="admin-select" name="cabang_id" aria-label="Filter cabang" data-auto-submit>
                <option value="">Semua Cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->cabang_id }}" @selected($selectedCabangId === $branch->cabang_id)>
                        {{ $branch->nama_cabang }}
                    </option>
                @endforeach
            </select>

            <div class="admin-date-range">
                <label class="admin-date-field">
                    <span>Dari</span>
                    <input class="admin-input" type="date" name="from" value="{{ $from }}">
                </label>

                <label class="admin-date-field">
                    <span>Sampai</span>
                    <input class="admin-input" type="date" name="to" value="{{ $to }}">
                </label>
            </div>

            <button class="admin-btn-primary" type="submit">Terapkan</button>

            @if ($isFiltered)
                <a class="admin-btn-ghost" href="{{ route('admin.report') }}">Reset</a>
            @endif

            <span class="admin-toolbar-meta">{{ $selectedBranchName }}</span>
        </form>
    </div>

    <div class="admin-kpi-grid cols-4">
        <div class="admin-stat-card">
            <div class="admin-stat-icon"><x-icon name="banknote" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Total Omzet</span>
                <strong class="admin-stat-value tabular-nums">Rp{{ number_format($totals['omzet'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub tabular-nums">{{ number_format($totals['transactions'], 0, ',', '.') }} transaksi berhasil</small>
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-icon"><x-icon name="receipt" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Estimasi HPP</span>
                <strong class="admin-stat-value tabular-nums">Rp{{ number_format($totals['hpp'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub">Berdasarkan resep dan harga beli</small>
            </div>
        </div>

        <div class="admin-stat-card {{ $totals['waste'] > 0 ? 'is-alert' : '' }}">
            <div class="admin-stat-icon"><x-icon name="triangle-alert" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Kerugian Waste</span>
                <strong class="admin-stat-value tabular-nums">Rp{{ number_format($totals['waste'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub">Bahan rusak atau kedaluwarsa</small>
            </div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-icon"><x-icon name="trending-up" size="1.25em" /></div>
            <div class="admin-stat-body">
                <span class="admin-stat-label">Estimasi Laba</span>
                <strong class="admin-stat-value tabular-nums">Rp{{ number_format($totals['laba'], 0, ',', '.') }}</strong>
                <small class="admin-stat-sub tabular-nums">Margin {{ number_format($totals['margin'], 1, ',', '.') }}%</small>
            </div>
        </div>
    </div>

    <div class="admin-report-grid">
        <div class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h3>Perbandingan Kinerja Cabang</h3>
                    <p>Ringkasan omzet, HPP, nilai waste, dan estimasi laba tiap cabang pada periode terpilih.</p>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Cabang</th>
                            <th scope="col" class="is-right">Transaksi</th>
                            <th scope="col" class="is-right">Omzet</th>
                            <th scope="col" class="is-right">HPP</th>
                            <th scope="col" class="is-right">Waste</th>
                            <th scope="col" class="is-right">Estimasi Laba</th>
                            <th scope="col" class="is-right">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($branchRows as $row)
                            <tr>
                                <td><span class="admin-cell-primary">{{ $row['nama_cabang'] }}</span></td>
                                <td class="is-right tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</td>
                                <td class="is-right tabular-nums">Rp{{ number_format($row['omzet'], 0, ',', '.') }}</td>
                                <td class="is-right tabular-nums">Rp{{ number_format($row['hpp'], 0, ',', '.') }}</td>
                                <td class="is-right tabular-nums">Rp{{ number_format($row['waste'], 0, ',', '.') }}</td>
                                <td class="is-right tabular-nums"><strong>Rp{{ number_format($row['laba'], 0, ',', '.') }}</strong></td>
                                <td class="is-right tabular-nums">{{ number_format($row['margin'], 1, ',', '.') }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-admin.empty-state
                                        icon="store"
                                        title="Belum ada data cabang"
                                        description="Tambahkan cabang terlebih dahulu untuk memantau perbandingan kinerja."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h3>Estimasi Laba Rugi per Hari</h3>
                    <p>Omzet dikurangi estimasi HPP dan kerugian waste harian.</p>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Tanggal</th>
                            <th scope="col" class="is-right">Transaksi</th>
                            <th scope="col" class="is-right">Omzet</th>
                            <th scope="col" class="is-right">HPP</th>
                            <th scope="col" class="is-right">Waste</th>
                            <th scope="col" class="is-right">Estimasi Laba</th>
                            <th scope="col" class="is-right">Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dailyRows as $row)
                            <tr>
                                <td><span class="admin-cell-primary">{{ \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('d M Y') }}</span></td>
                                <td class="is-right tabular-nums">{{ number_format($row['count'], 0, ',', '.') }}</td>
                                <td class="is-right tabular-nums">Rp{{ number_format($row['omzet'], 0, ',', '.') }}</td>
                                <td class="is-right tabular-nums">Rp{{ number_format($row['hpp'], 0, ',', '.') }}</td>
                                <td class="is-right tabular-nums">Rp{{ number_format($row['waste'], 0, ',', '.') }}</td>
                                <td class="is-right tabular-nums"><strong>Rp{{ number_format($row['laba'], 0, ',', '.') }}</strong></td>
                                <td class="is-right tabular-nums">{{ number_format($row['margin'], 1, ',', '.') }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-admin.empty-state
                                        icon="calendar-days"
                                        title="Belum ada transaksi pada periode ini"
                                        description="Ubah rentang tanggal atau pilih cabang lain untuk melihat riwayat harian."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $dailyRows->links('components.admin.pagination') }}
        </div>

        <div class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h3>Mutasi Stok per Bahan</h3>
                    <p>Pergerakan stok masuk dari penerimaan cabang, stok keluar penjualan, waste, dan stok cabang saat ini.</p>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Bahan Baku</th>
                            <th scope="col" class="is-right">Stok Masuk</th>
                            <th scope="col" class="is-right">Stok Keluar</th>
                            <th scope="col" class="is-right">Waste</th>
                            <th scope="col" class="is-right">Stok Saat Ini</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stockRows as $row)
                            <tr>
                                <td><span class="admin-cell-primary">{{ $row['name'] }}</span></td>
                                <td class="is-right tabular-nums">{{ $fmtNum($row['masuk']) }} {{ $row['satuan'] }}</td>
                                <td class="is-right tabular-nums">{{ $fmtNum($row['keluar']) }} {{ $row['satuan'] }}</td>
                                <td class="is-right tabular-nums">{{ $fmtNum($row['waste']) }} {{ $row['satuan'] }}</td>
                                <td class="is-right tabular-nums"><strong>{{ $fmtNum($row['stok_saat_ini']) }} {{ $row['satuan'] }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-admin.empty-state
                                        icon="package"
                                        title="Belum ada mutasi stok pada periode ini"
                                        description="Mutasi akan muncul setelah cabang melakukan penerimaan barang, penjualan, atau pencatatan waste."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $stockRows->links('components.admin.pagination') }}
        </div>

        <div class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h3>Penjualan per Menu</h3>
                    <p>Produk terjual pada periode terpilih, diurutkan dari jumlah porsi terbanyak.</p>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Menu Produk</th>
                            <th scope="col" class="is-right">Terjual</th>
                            <th scope="col" class="is-right">Omzet</th>
                            <th scope="col" class="is-right">Estimasi HPP</th>
                            <th scope="col" class="is-right">Laba Kotor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($menuRows as $row)
                            <tr>
                                <td><span class="admin-cell-primary">{{ $row['name'] }}</span></td>
                                <td class="is-right tabular-nums">{{ number_format($row['qty'], 0, ',', '.') }} porsi</td>
                                <td class="is-right tabular-nums">Rp{{ number_format($row['omzet'], 0, ',', '.') }}</td>
                                <td class="is-right tabular-nums">Rp{{ number_format($row['hpp'], 0, ',', '.') }}</td>
                                <td class="is-right tabular-nums"><strong>Rp{{ number_format($row['laba'], 0, ',', '.') }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-admin.empty-state
                                        icon="coffee"
                                        title="Belum ada penjualan menu pada periode ini"
                                        description="Data akan terisi otomatis saat kasir menyelesaikan transaksi pembayaran."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $menuRows->links('components.admin.pagination') }}
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => select.form?.submit());
    });
</script>
@endpush
