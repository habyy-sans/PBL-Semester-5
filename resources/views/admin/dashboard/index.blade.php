@extends('layouts.app')

@section('title', 'Dashboard Admin')
@section('description', 'Ringkasan operasional dan kondisi stok seluruh lokasi.')

@section('content')
<section class="page active admin-panel">
    <div class="admin-page-heading">
        <div>
            <h1>Ringkasan operasional</h1>
            <p>Pantau data master serta bahan yang perlu segera diperhatikan.</p>
        </div>
        <a class="primary-button" href="{{ route('admin.pengguna.create') }}">+ Tambah Pengguna</a>
    </div>

    <div class="stats-grid admin-kpi-grid">
        <a class="stat-card" href="{{ route('admin.pengguna.index') }}">
            <div class="stat-icon">▣</div>
            <div class="stat-content"><span class="stat-label">Total Pengguna</span><strong>{{ number_format($summary['users']) }}</strong><small>Seluruh role</small></div>
        </a>
        <a class="stat-card" href="{{ route('admin.cabang.index') }}">
            <div class="stat-icon">⌂</div>
            <div class="stat-content"><span class="stat-label">Total Cabang</span><strong>{{ number_format($summary['cabang']) }}</strong><small>Lokasi operasional</small></div>
        </a>
        <a class="stat-card" href="{{ route('admin.dc.index') }}">
            <div class="stat-icon">▦</div>
            <div class="stat-content"><span class="stat-label">Total DC</span><strong>{{ number_format($summary['dc']) }}</strong><small>Distribution center</small></div>
        </a>
        <a class="stat-card" href="{{ route('admin.bahan-baku.index') }}">
            <div class="stat-icon">▤</div>
            <div class="stat-content"><span class="stat-label">Bahan Baku</span><strong>{{ number_format($summary['bahan']) }}</strong><small>Item bahan terdaftar</small></div>
        </a>
        <a class="stat-card" href="{{ route('admin.menu-produk.index') }}">
            <div class="stat-icon">≡</div>
            <div class="stat-content"><span class="stat-label">Menu Produk</span><strong>{{ number_format($summary['menu']) }}</strong><small>Produk terdaftar</small></div>
        </a>
        <a class="stat-card admin-kpi-alert" href="{{ route('admin.stok-cabang.index', ['status' => 'menipis']) }}">
            <div class="stat-icon">!</div>
            <div class="stat-content"><span class="stat-label">Stok Menipis</span><strong>{{ number_format($summary['low_stock']) }}</strong><small>Di bawah minimum</small></div>
        </a>
    </div>

    <div class="dashboard-card admin-low-stock-card">
        <div class="card-header">
            <div>
                <h3>Bahan di bawah stok minimum</h3>
                <p>Gabungan pemantauan cabang dan distribution center.</p>
            </div>
            <div class="admin-card-links">
                <a href="{{ route('admin.stok-cabang.index', ['status' => 'menipis']) }}">Stok Cabang</a>
                <a href="{{ route('admin.stok-dc.index', ['status' => 'menipis']) }}">Stok DC</a>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr><th>Lokasi</th><th>Bahan</th><th>Jumlah stok</th><th>Stok minimum</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($lowStock as $stock)
                        <tr>
                            <td><span class="admin-location-type">{{ $stock['lokasi_label'] }}</span> {{ $stock['lokasi'] }}</td>
                            <td>{{ $stock['bahan'] }}</td>
                            <td>{{ $stock['jumlah'] }} {{ $stock['satuan'] }}</td>
                            <td>{{ $stock['minimum'] }} {{ $stock['satuan'] }}</td>
                            <td><span class="status warning">Menipis</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="page-empty">Semua stok berada di atas batas minimum.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
