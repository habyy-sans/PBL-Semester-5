@extends('layouts.app')

@section('title', 'Laporan Cabang')
@section('description', 'Pantau performa keuangan, penjualan, dan pergerakan stok cabang KopSync.')

@section('content')

<div class="dash-wrapper report-page">

    {{-- HEADER PERIODE LAPORAN --}}
    <section class="dash-card report-period-card">
        <div class="dash-card-header">
            <div class="dash-card-title-group">
                <div class="dash-card-icon">📅</div>
                <div>
                    <h3 class="dash-card-title">Periode Laporan</h3>
                    <p class="report-section-desc">
                        Pilih rentang waktu untuk melihat performa cabang.
                    </p>
                    <p class="report-section-desc">
                        Laporan diakses pada:
                        {{ now()->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i:s') }} WIB
                    </p>
                </div>
            </div>

            <a
                class="report-export-btn"
                href="{{ route('manager.report', [
                    'from' => $from,
                    'to' => $to,
                    'export' => 1
                ]) }}"
            >
                <span>📥</span> Unduh CSV
            </a>
        </div>

        <form
            method="GET"
            action="{{ route('manager.report') }}"
            class="report-filter-form"
        >
            <div class="report-date-field">
                <label for="report-from">Dari tanggal</label>
                <input
                    id="report-from"
                    type="date"
                    name="from"
                    value="{{ $from }}"
                    required
                >
            </div>

            <div class="report-date-separator">—</div>

            <div class="report-date-field">
                <label for="report-to">Sampai tanggal</label>
                <input
                    id="report-to"
                    type="date"
                    name="to"
                    value="{{ $to }}"
                    min="{{ $from }}"
                    required
                >
            </div>

            <button type="submit" class="report-apply-btn">
                Terapkan Filter
                <span>→</span>
            </button>
        </form>
    </section>


    {{-- KPI SUMMARY --}}
    <section class="report-kpi-row">

        {{-- TOTAL OMZET --}}
        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <div class="dash-kpi-label-group">
                    <span class="dash-kpi-icon">💵</span>
                    <span>Total Omzet</span>
                </div>
                <span class="dash-kpi-link">Revenue</span>
            </div>

            <div class="dash-kpi-bottom">
                <div class="dash-kpi-value">
                    Rp {{ number_format($totals['omzet'], 0, ',', '.') }}
                </div>
                <span class="dash-kpi-subtext">
                    {{ number_format($totals['transactions']) }} transaksi berhasil
                </span>
            </div>
        </div>

        {{-- TOTAL HPP --}}
        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <div class="dash-kpi-label-group">
                    <span class="dash-kpi-icon">📦</span>
                    <span>Total HPP</span>
                </div>
                <span class="dash-kpi-link">Cost</span>
            </div>

            <div class="dash-kpi-bottom">
                <div class="dash-kpi-value">
                    Rp {{ number_format($totals['hpp'], 0, ',', '.') }}
                </div>
                <span class="dash-kpi-subtext">
                    Estimasi biaya bahan terjual
                </span>
            </div>
        </div>

        {{-- WASTE --}}
        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <div class="dash-kpi-label-group">
                    <span class="dash-kpi-icon">⚠️</span>
                    <span>Kerugian Waste</span>
                </div>
                <span class="dash-kpi-link">Waste</span>
            </div>

            <div class="dash-kpi-bottom">
                <div class="dash-kpi-value report-danger-value">
                    Rp {{ number_format($totals['waste'], 0, ',', '.') }}
                </div>
                <span class="dash-kpi-badge danger">
                    Biaya bahan terbuang
                </span>
            </div>
        </div>

        {{-- ESTIMASI LABA --}}
        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <div class="dash-kpi-label-group">
                    <span class="dash-kpi-icon">📈</span>
                    <span>Estimasi Laba</span>
                </div>
                <span class="dash-kpi-link">Profit</span>
            </div>

            <div class="dash-kpi-bottom">
                <div class="dash-kpi-value">
                    Rp {{ number_format($totals['laba'], 0, ',', '.') }}
                </div>
                <span class="dash-kpi-badge positive">
                    Margin {{ number_format($totals['margin'], 1, ',', '.') }}%
                </span>
            </div>
        </div>

    </section>


    {{-- LABA RUGI HARIAN --}}
    <section class="dash-card report-section-card">

        <div class="dash-card-header">
            <div class="dash-card-title-group">
                <div class="dash-card-icon">📊</div>
                <div>
                    <h3 class="dash-card-title">Estimasi Laba Rugi Harian</h3>
                    <p class="report-section-desc">
                        Ringkasan omzet, HPP, waste, dan estimasi laba setiap hari.
                    </p>
                </div>
            </div>

            <span class="report-section-count">
                {{ $dailyRows->total() }} Hari
            </span>
        </div>

        <div class="dash-table-wrap">
            <table class="dash-table report-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th class="text-right">Transaksi</th>
                        <th class="text-right">Omzet</th>
                        <th class="text-right">HPP</th>
                        <th class="text-right">Waste</th>
                        <th class="text-right">Estimasi Laba</th>
                        <th class="text-right">Margin</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($dailyRows as $row)
                        <tr>
                            <td>
                                <strong>
                                    {{ \Illuminate\Support\Carbon::parse($row['date'])->translatedFormat('d M Y') }}
                                </strong>
                            </td>

                            <td class="text-right">
                                {{ number_format($row['count']) }}
                            </td>

                            <td class="text-right">
                                Rp {{ number_format($row['omzet'], 0, ',', '.') }}
                            </td>

                            <td class="text-right">
                                Rp {{ number_format($row['hpp'], 0, ',', '.') }}
                            </td>

                            <td class="text-right report-danger-text">
                                Rp {{ number_format($row['waste'], 0, ',', '.') }}
                            </td>

                            <td class="text-right">
                                <strong>
                                    Rp {{ number_format($row['laba'], 0, ',', '.') }}
                                </strong>
                            </td>

                            <td class="text-right">
                                {{ number_format($row['margin'], 1, ',', '.') }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="report-empty-cell">
                                <div class="report-empty-state">
                                    <span>📊</span>
                                    <strong>Belum ada data laporan</strong>
                                    <p>
                                        Tidak ditemukan transaksi atau waste
                                        pada periode yang dipilih.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="report-table-footer">
            <span>
                Menampilkan {{ $dailyRows->firstItem() ?? 0 }}–{{ $dailyRows->lastItem() ?? 0 }}
                dari {{ $dailyRows->total() }} hari
            </span>

            {{ $dailyRows->links() }}
        </div>

    </section>


    {{-- MUTASI STOK --}}
    <section class="dash-card report-section-card">

        <div class="dash-card-header">
            <div class="dash-card-title-group">
                <div class="dash-card-icon">📦</div>
                <div>
                    <h3 class="dash-card-title">Mutasi Stok per Bahan</h3>
                    <p class="report-section-desc">
                        Pergerakan stok masuk, keluar, waste, dan stok cabang saat ini.
                    </p>
                </div>
            </div>

            <span class="report-section-count">
                {{ $stockRows->total() }} Bahan
            </span>
        </div>

        <div class="dash-table-wrap">
            <table class="dash-table report-table">
                <thead>
                    <tr>
                        <th>Bahan Baku</th>
                        <th class="text-right">Stok Masuk</th>
                        <th class="text-right">Stok Keluar</th>
                        <th class="text-right">Waste</th>
                        <th class="text-right">Stok Saat Ini</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($stockRows as $row)
                        <tr>
                            <td>
                                <strong>{{ $row['name'] }}</strong>
                            </td>

                            <td class="text-right">
                                {{ number_format($row['masuk'], 3, ',', '.') }}
                            </td>

                            <td class="text-right">
                                {{ number_format($row['keluar'], 3, ',', '.') }}
                            </td>

                            <td class="text-right report-danger-text">
                                {{ number_format($row['waste'], 3, ',', '.') }}
                            </td>

                            <td class="text-right">
                                <strong>
                                    {{ number_format($row['stok_saat_ini'], 3, ',', '.') }}
                                </strong>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="report-empty-cell">
                                <div class="report-empty-state">
                                    <span>📦</span>
                                    <strong>Belum ada data mutasi stok</strong>
                                    <p>
                                        Tidak ditemukan pergerakan stok pada periode ini.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="report-table-footer">
            <span>
                Menampilkan {{ $stockRows->firstItem() ?? 0 }}–{{ $stockRows->lastItem() ?? 0 }}
                dari {{ $stockRows->total() }} bahan
            </span>

            {{ $stockRows->links() }}
        </div>

    </section>


    {{-- PENJUALAN PER MENU --}}
    <section class="dash-card report-section-card">

        <div class="dash-card-header">
            <div class="dash-card-title-group">
                <div class="dash-card-icon">🎯</div>
                <div>
                    <h3 class="dash-card-title">Penjualan per Menu</h3>
                    <p class="report-section-desc">
                        Performa penjualan menu berdasarkan jumlah unit terjual.
                    </p>
                </div>
            </div>

            <span class="report-section-count">
                {{ $menuRows->total() }} Menu
            </span>
        </div>

        <div class="dash-table-wrap">
            <table class="dash-table report-table">
                <thead>
                    <tr>
                        <th>Nama Menu</th>
                        <th class="text-right">Terjual</th>
                        <th class="text-right">Omzet</th>
                        <th class="text-right">HPP</th>
                        <th class="text-right">Laba Kotor</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($menuRows as $row)
                        <tr>
                            <td>
                                <strong>{{ $row['name'] }}</strong>
                            </td>

                            <td class="text-right">
                                {{ number_format($row['qty']) }} porsi
                            </td>

                            <td class="text-right">
                                Rp {{ number_format($row['omzet'], 0, ',', '.') }}
                            </td>

                            <td class="text-right">
                                Rp {{ number_format($row['hpp'], 0, ',', '.') }}
                            </td>

                            <td class="text-right">
                                <strong>
                                    Rp {{ number_format($row['laba'], 0, ',', '.') }}
                                </strong>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="report-empty-cell">
                                <div class="report-empty-state">
                                    <span>🎯</span>
                                    <strong>Belum ada data penjualan menu</strong>
                                    <p>
                                        Tidak ditemukan penjualan menu pada periode ini.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="report-table-footer">
            <span>
                Menampilkan {{ $menuRows->firstItem() ?? 0 }}–{{ $menuRows->lastItem() ?? 0 }}
                dari {{ $menuRows->total() }} menu
            </span>

            {{ $menuRows->links() }}
        </div>

    </section>


    {{-- CATATAN LAPORAN --}}
    <div class="report-disclaimer">
        <span>ℹ️</span>
        <p>
            HPP dan estimasi laba dihitung berdasarkan harga bahan baku
            serta resep yang tersedia saat laporan diakses. Nilai ini
            merupakan estimasi, bukan laporan akuntansi historis.
        </p>
    </div>

</div>

@endsection