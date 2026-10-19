@extends('layouts.app')

@section('title', 'Dashboard')
@section('description', 'Pantau operasional KopSync secara real-time.')

@section('content')

@php
    $branchName = $branchName ?? 'Cabang';
    $storeName  = $storeName ?? 'Store';
@endphp

<section class="page active">

    <!-- INFO STORE -->
    <div class="table-toolbar">

        <span class="table-meta" id="dataDate" data-store="{{ $storeName }}"></span>

    </div>


    <!-- STAT CARDS -->
    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">Rp</div>
            <div class="stat-content">
                <span class="stat-label">Omzet Hari Ini</span>
                <strong id="statOmzet">Rp 0</strong>
                <small id="statOmzetDelta" class="positive">-</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">≡</div>
            <div class="stat-content">
                <span class="stat-label">Stok Menipis</span>
                <strong id="statLow">0 Item</strong>
                <small id="statLowSub" class="warning">-</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">↻</div>
            <div class="stat-content">
                <span class="stat-label">Pesanan DC</span>
                <strong id="statDC">0 Pesanan</strong>
                <small id="statDCSub">-</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">△</div>
            <div class="stat-content">
                <span class="stat-label">Waste Hari Ini</span>
                <strong id="statWaste">Rp 0</strong>
                <small id="statWasteSub" class="danger-text">-</small>
            </div>
        </div>

    </div>


    <!-- CHART GRID -->
    <div class="dashboard-grid">

        <!-- STOCK CHART -->
        <div class="dashboard-card stock-card">

            <div class="card-header">
                <div>
                    <h3>Pergerakan Stok</h3>
                    <p>Stok masuk, stok keluar, dan waste</p>
                </div>

                <select id="stockPeriod" class="select">
                    <option value="7">7 Hari</option>
                    <option value="30">30 Hari</option>
                </select>
            </div>

            <div class="chart-container stock-chart-container">
                <canvas id="stockChart"></canvas>
            </div>

            <div class="chart-legend">
                <div>
                    <span class="legend-dot masuk"></span>
                    Stok Masuk
                </div>

                <div>
                    <span class="legend-dot keluar"></span>
                    Stok Keluar
                </div>

                <div>
                    <span class="legend-dot" style="background: var(--red)"></span>
                    Waste
                </div>
            </div>

        </div>


        <!-- OMZET CHART -->
        <div class="dashboard-card omzet-card">

            <div class="card-header">
                <div>
                    <h3>Perkembangan Omzet</h3>
                    <p>Monitoring omzet berdasarkan periode</p>
                </div>
            </div>

            <div class="period-tabs">
                <button class="period-btn active" data-period="daily">Harian</button>
                <button class="period-btn" data-period="monthly">Bulanan</button>
                <button class="period-btn" data-period="yearly">Tahunan</button>
            </div>

            <div class="chart-container omzet-chart-container">
                <canvas id="omzetChart"></canvas>
            </div>

        </div>

    </div>


    <!-- ALERT + PROFIT -->
    <div class="bottom-grid">

        <!-- ALERT -->
        <div class="dashboard-card">

            <div class="card-header">
                <div>
                    <h3>Alert Terbaru</h3>
                    <p>Perlu perhatian</p>
                </div>

                <span class="alert-count" id="alertCount">0</span>
            </div>

            <div class="alert-list" id="alertList"></div>

        </div>


        <!-- PROFIT -->
        <div class="dashboard-card">

            <div class="card-header">
                <div>
                    <h3>Estimasi Laba Rugi</h3>
                    <p>Berdasarkan omzet, HPP, dan waste</p>
                </div>

                <button class="outline-button" type="button" onclick="window.location.href='{{ route('manager.report') }}'">Lihat Detail</button>
            </div>

            <div class="profit-summary">

                <div>
                    <span>Omzet</span>
                    <strong id="profitOmzet">Rp 0</strong>
                </div>

                <div>
                    <span>HPP</span>
                    <strong id="profitHpp">Rp 0</strong>
                </div>

                <div>
                    <span>Waste</span>
                    <strong class="danger-text" id="profitWaste">Rp 0</strong>
                </div>

                <div class="profit-total">
                    <span>Estimasi Laba</span>
                    <strong id="profitLaba">Rp 0</strong>
                </div>

            </div>

        </div>

    </div>

</section>

<script>
    // Data dari backend (lihat Manager\DashboardController)
    window.KOPSYNC = @json($payload);
</script>

@endsection

@push('scripts')
    @vite('resources/js/pages/dashboard.js')
@endpush