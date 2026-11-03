@extends('layouts.app')

@section('title', 'Dashboard')
@section('description', 'Pantau performa penjualan, kondisi stok, dan pergerakan gerai KopSync.')

@section('content')

@php
    $branchName = $branchName ?? 'Cabang';
    $storeName = $storeName ?? 'Store';
@endphp

<div class="dash-wrapper">

    <!-- HIDDEN META FOR DATE BINDING -->
    <span id="dataDate" data-store="{{ $storeName }}" style="display: none;"></span>

    <!-- TOP KPI STATS (5 Horizontal Cards - Sesuai Template Image 2) -->
    <section class="dash-kpi-row">

        <!-- CARD 1: TOTAL REVENUE / OMZET -->
        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <div class="dash-kpi-label-group">
                    <span class="dash-kpi-icon">💵</span>
                    <span>Total Revenue</span>
                </div>
                <a href="{{ route('manager.report') }}" class="dash-kpi-link">Details</a>
            </div>
            <div class="dash-kpi-bottom">
                <div class="dash-kpi-value" id="statOmzet">Rp 0</div>
                <span class="dash-kpi-badge positive" id="statOmzetDelta">+0%</span>
            </div>
        </div>

        <!-- CARD 2: ON PROGRESS / PESANAN DC -->
        <a href="{{ route('manager.restock.draft') }}" class="dash-kpi-card">
            <div class="dash-kpi-top">
                <div class="dash-kpi-label-group">
                    <span class="dash-kpi-icon">📦</span>
                    <span>Pesanan DC</span>
                </div>
                <span class="dash-kpi-link">Restock</span>
            </div>
            <div class="dash-kpi-bottom">
                <div class="dash-kpi-value" id="statDC">0 Pesanan</div>
                <span class="dash-kpi-subtext" id="statDCSub">Menunggu proses</span>
            </div>
        </a>

        <!-- CARD 3: PERFORMANCE / KONDISI STOK -->
        <a href="{{ route('manager.alert') }}" class="dash-kpi-card">
            <div class="dash-kpi-top">
                <div class="dash-kpi-label-group">
                    <span class="dash-kpi-icon">📊</span>
                    <span>Kondisi Stok</span>
                </div>
                <span class="dash-kpi-link">Alerts</span>
            </div>
            <div class="dash-kpi-bottom">
                <div class="dash-kpi-value" id="statLow">0 Item</div>
                <span class="dash-kpi-badge warning" id="statLowSub">Batas aman</span>
            </div>
        </a>

        <!-- CARD 4: TODAY SALES / TRANSAKSI HARI INI -->
        <div class="dash-kpi-card">
            <div class="dash-kpi-top">
                <div class="dash-kpi-label-group">
                    <span class="dash-kpi-icon">☕</span>
                    <span>Today Sales</span>
                </div>
                <span class="dash-kpi-link">Kasir</span>
            </div>
            <div class="dash-kpi-bottom">
                <div class="dash-kpi-value" id="statSalesCount">0 Transaksi</div>
                <span class="dash-kpi-badge positive">Aktif</span>
            </div>
        </div>

        <!-- CARD 5: WASTE / EFISIENSI -->
        <a href="{{ route('manager.report') }}" class="dash-kpi-card">
            <div class="dash-kpi-top">
                <div class="dash-kpi-label-group">
                    <span class="dash-kpi-icon">⚠️</span>
                    <span>Waste Terkini</span>
                </div>
                <span class="dash-kpi-link">Laporan</span>
            </div>
            <div class="dash-kpi-bottom">
                <div class="dash-kpi-value" id="statWaste">Rp 0</div>
                <span class="dash-kpi-badge danger" id="statWasteSub">0 Laporan</span>
            </div>
        </a>

    </section>

    <!-- MIDDLE ROW: SALES STATISTIC (WIDE) + SCORE & ALERTS (NARROW) -->
    <div class="dash-middle-row">

        <!-- SALES STATISTIC CHART CARD -->
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title-group">
                    <div class="dash-card-icon">📈</div>
                    <h3 class="dash-card-title">Sales Statistic</h3>
                </div>

                <!-- CATEGORY LEGEND -->
                <div class="dash-chart-legend">
                    <div class="dash-legend-item">
                        <span class="dash-legend-dot" style="background: #532508;"></span>
                        <span>Coffee</span>
                    </div>
                    <div class="dash-legend-item">
                        <span class="dash-legend-dot" style="background: #d99a4e;"></span>
                        <span>Non-Coffee</span>
                    </div>
                    <div class="dash-legend-item">
                        <span class="dash-legend-dot" style="background: #758f3c;"></span>
                        <span>Snack / Makanan</span>
                    </div>
                </div>

                <!-- PERIOD SWITCHER -->
                <div class="dash-period-pills">
                    <button type="button" class="dash-period-btn period-btn active" data-period="daily">Day</button>
                    <button type="button" class="dash-period-btn period-btn" data-period="monthly">Month</button>
                    <button type="button" class="dash-period-btn period-btn" data-period="yearly">Year</button>
                </div>
            </div>

            <div class="dash-chart-container">
                <canvas id="omzetChart"></canvas>
            </div>
        </div>

        <!-- SCORE & QUALITY CARD (Sesuai Template Image 2) -->
        <div class="dash-card dash-score-card">
            <div class="dash-card-header">
                <div class="dash-card-title-group">
                    <div class="dash-card-icon">⭐</div>
                    <h3 class="dash-card-title">Score & Quality</h3>
                </div>
                <span class="dash-kpi-link" id="alertCountBadge"><span id="alertCount">0</span> Alert</span>
            </div>

            <!-- GAUGE SCORE PROGRESS -->
            <div class="dash-score-gauge-wrap">
                <div class="dash-score-number" id="storeScore">98</div>
                <div class="dash-score-meta">
                    <strong>Indeks Kinerja Gerai</strong>
                    <span id="storeScoreDesc">Operasional prima & terjaga</span>
                </div>
            </div>

            <!-- ACTIONABLE COMPLAINTS / ALERTS -->
            <div class="dash-alert-list" id="alertList">
                <!-- Rendered dynamically via JS -->
            </div>
        </div>

    </div>

    <!-- BOTTOM ROW: ITEMS PERFORMANCE (RADAR) + RECENT TRANSACTIONS (TABLE) -->
    <div class="dash-bottom-row">

        <!-- ITEMS PERFORMANCE (RADAR CHART) -->
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title-group">
                    <div class="dash-card-icon">🎯</div>
                    <h3 class="dash-card-title">Items Performance</h3>
                </div>
                <span class="dash-kpi-subtext">Proporsi Pesanan</span>
            </div>

            <div class="dash-radar-container">
                <canvas id="radarChart"></canvas>
            </div>
        </div>

        <!-- RECENT TRANSACTIONS TABLE -->
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title-group">
                    <div class="dash-card-icon">📑</div>
                    <h3 class="dash-card-title">Recent Transaction</h3>
                </div>

                <!-- CATEGORY TABS -->
                <div class="dash-trx-filters" id="trxFilterTabs">
                    <button type="button" class="dash-trx-filter-btn active" data-trx-cat="All">All</button>
                    <button type="button" class="dash-trx-filter-btn" data-trx-cat="Coffee">Coffee</button>
                    <button type="button" class="dash-trx-filter-btn" data-trx-cat="Tea">Tea</button>
                    <button type="button" class="dash-trx-filter-btn" data-trx-cat="Snack">Snack</button>
                </div>
            </div>

            <div class="dash-table-wrap">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th style="width: 36px;"><input type="checkbox" id="checkAllTrx" aria-label="Pilih semua"></th>
                            <th>Customer / Kasir</th>
                            <th>Waktu</th>
                            <th>Menu Pesanan</th>
                            <th style="text-align: right;">Total</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="recentTrxBody">
                        <!-- Rendered dynamically via JS -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- HIDDEN STOCK CHART CANVAS UNTUK MAINTAIN LOGIKA JS LAMA -->
    <div style="display: none;">
        <canvas id="stockChart"></canvas>
        <select id="stockPeriod"><option value="7">7</option></select>
        <span id="profitOmzet"></span>
        <span id="profitHpp"></span>
        <span id="profitWaste"></span>
        <span id="profitLaba"></span>
    </div>

</div>

<script>
    // Data dari backend (lihat Manager\DashboardController)
    window.KOPSYNC = @json($payload);
</script>

@endsection

@push('scripts')
    @vite('resources/js/pages/dashboard.js')
@endpush
