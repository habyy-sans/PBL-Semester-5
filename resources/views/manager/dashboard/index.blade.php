@extends('layouts.app')

@section('title', 'Dashboard')
@section('description', 'Pantau operasional KopSync secara real-time.')

@section('content')

<section class="page active">

    <!-- STAT CARDS -->
    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">Rp</div>
            <div class="stat-content">
                <span class="stat-label">Omzet Hari Ini</span>
                <strong>Rp 4,82 jt</strong>
                <small class="positive">+8,4% dari kemarin</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">≡</div>
            <div class="stat-content">
                <span class="stat-label">Stok Menipis</span>
                <strong>8 Item</strong>
                <small class="warning">3 perlu approval</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">↻</div>
            <div class="stat-content">
                <span class="stat-label">Pesanan DC</span>
                <strong>5 Pesanan</strong>
                <small>2 sedang dikirim</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">△</div>
            <div class="stat-content">
                <span class="stat-label">Waste Hari Ini</span>
                <strong>Rp 185 rb</strong>
                <small class="danger-text">2 laporan baru</small>
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
                    <p>Stok masuk dan stok keluar</p>
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

                <span class="alert-count">5</span>
            </div>

            <div class="alert-list">

                <div class="alert-item">
                    <span class="alert-dot orange"></span>
                    <div>
                        <strong>Fresh Milk</strong>
                        <p>Stok di bawah minimum</p>
                    </div>
                </div>

                <div class="alert-item">
                    <span class="alert-dot red"></span>
                    <div>
                        <strong>Waste — Sirup</strong>
                        <p>2 botol dilaporkan rusak</p>
                    </div>
                </div>

                <div class="alert-item">
                    <span class="alert-dot brown"></span>
                    <div>
                        <strong>Draft Restock #RS-024</strong>
                        <p>Menunggu approval Manager</p>
                    </div>
                </div>

                <div class="alert-item">
                    <span class="alert-dot green"></span>
                    <div>
                        <strong>Pengiriman #SJ-018</strong>
                        <p>Dalam perjalanan</p>
                    </div>
                </div>

            </div>

        </div>


        <!-- PROFIT -->
        <div class="dashboard-card">

            <div class="card-header">
                <div>
                    <h3>Estimasi Laba Rugi</h3>
                    <p>Berdasarkan omzet, HPP, dan waste</p>
                </div>

                <button class="outline-button">Lihat Detail</button>
            </div>

            <div class="profit-summary">

                <div>
                    <span>Omzet</span>
                    <strong>Rp 4.820.000</strong>
                </div>

                <div>
                    <span>HPP</span>
                    <strong>Rp 1.930.000</strong>
                </div>

                <div>
                    <span>Waste</span>
                    <strong class="danger-text">Rp 185.000</strong>
                </div>

                <div class="profit-total">
                    <span>Estimasi Laba</span>
                    <strong>Rp 2.705.000</strong>
                </div>

            </div>

        </div>

    </div>

</section>

@endsection

@push('scripts')
    @vite('resources/js/pages/dashboard.js')
@endpush