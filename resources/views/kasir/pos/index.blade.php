@extends('layouts.app')
 
@section('title', 'Kasir')
@section('description', 'Kelola transaksi penjualan pelanggan.')
 
@section('content')
 
<section class="page active">
 
    <div class="page-card">
 
        <div class="page-toolbar">
            <button class="primary-button">+ Transaksi Baru</button>
        </div>
 
        <div class="pos-layout">
 
            <div class="product-grid">
 
                <div class="product-card">
                    <div class="product-image">☕</div>
                    <strong>Americano</strong>
                    <span>Rp 18.000</span>
                </div>
 
                <div class="product-card">
                    <div class="product-image">🥛</div>
                    <strong>Caffe Latte</strong>
                    <span>Rp 22.000</span>
                </div>
 
                <div class="product-card">
                    <div class="product-image">🍫</div>
                    <strong>Chocolate</strong>
                    <span>Rp 20.000</span>
                </div>
 
                <div class="product-card">
                    <div class="product-image">🍵</div>
                    <strong>Matcha</strong>
                    <span>Rp 23.000</span>
                </div>
 
            </div>
 
            <div class="cart-card">
                <h4>Pesanan</h4>
                <div class="cart-empty">Belum ada produk</div>
            </div>
 
        </div>
 
    </div>
 
</section>
 
@endsection
 