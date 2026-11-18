@extends('layouts.app')

@section('title', 'Role & Hak Akses')
@section('description', 'Role sistem yang dapat diberikan kepada pengguna.')

@section('content')
<section class="page active admin-panel">
    <div class="admin-page-heading">
        <a class="primary-button" href="{{ route('admin.pengguna.create') }}">+ Tetapkan Role</a>
    </div>
    <div class="admin-role-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-top: 16px;">
        @foreach (config('menu.roles') as $key => $role)
        @php
                // Mapping deskripsi fungsi role
                $descriptions = [
                    'kasir'   => 'Akses transaksi kasir POS dan cetak struk penjualan.',
                    'ic'      => 'Manajemen stok bahan baku & operasional shift cabang.',
                    'manager' => 'Monitoring laporan transaksi & analisis kinerja cabang.',
                    'dc'      => 'Kelola stok gudang pusat & pengiriman antar cabang.',
                    'admin'   => 'Akses penuh ke seluruh konfigurasi & pengaturan sistem.',
                ];
            @endphp
            <article class="page-card admin-role-card" style="display: flex; flex-direction: column; justify-content: space-between; padding: 20px; height: 100%;">
                <div>
                <span class="status info" style="display: inline-block; margin-bottom: 10px; text-transform: lowercase;">{{ $key }}</span>
                <h2 style="margin: 0 0 6px 0; font-size: 18px; font-weight: 700; color: var(--pos-brown-dark, #4A2C11);">{{ $role['label'] }}</h2>
                <p style="margin: 0 0 16px 0; font-size: 13px; color: var(--pos-muted, #6B7280); line-height: 1.4;">
                        {{ $descriptions[$key] ?? 'Hak akses operasional sistem KopSync.' }}
                    </p>
            </div>
                <div style="border-top: 1px dashed var(--pos-border, #E5E7EB); padding-top: 12px; margin-top: auto;">
                    <a href="{{ route('admin.pengguna.index', ['role' => $key]) }}" style="font-size: 13px; font-weight: 600; color: var(--pos-brown-dark, #4A2C11); text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                        Lihat pengguna dengan role ini →
                    </a>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endsection
