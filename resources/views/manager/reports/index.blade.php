@extends('layouts.app')

@section('title', 'Laporan')
@section('description', 'Pantau laporan operasional cabang.')

@section('content')

<section class="page active">

    <div class="page-card">

        <div class="page-card-header">
            <div>
                <h3>Laporan</h3>
                <p>Monitoring laporan operasional cabang.</p>
            </div>
        </div>

        <div class="report-grid">

            <div class="report-box">
                <span>Penjualan</span>
                <strong>Rp 4,82 jt</strong>
            </div>

            <div class="report-box">
                <span>Total Waste</span>
                <strong>Rp 185 rb</strong>
            </div>

            <div class="report-box">
                <span>Pesanan DC</span>
                <strong>5 Pesanan</strong>
            </div>

            <div class="report-box">
                <span>Stok Menipis</span>
                <strong>8 Item</strong>
            </div>

        </div>

    </div>

</section>

@endsection