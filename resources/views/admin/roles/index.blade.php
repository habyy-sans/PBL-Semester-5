@extends('layouts.app')

@section('title', 'Role & Hak Akses')
@section('description', 'Ringkasan pembagian wewenang, cakupan akses, dan modul tiap role di KopSync.')

@push('header_custom')
    <a class="admin-btn-primary" href="{{ route('admin.pengguna.create') }}">
        <x-icon name="plus" size="1em" />
        <span>Tetapkan Role</span>
    </a>
@endpush

@section('content')
<section class="page active admin-panel">
    <div class="admin-role-grid">
        @foreach ($roles as $role)
            <article class="admin-role-card">
                <div class="admin-role-top">
                    <x-admin.status-badge variant="info">{{ $role['key'] }}</x-admin.status-badge>
                    <x-admin.status-badge variant="{{ $role['active_count'] > 0 ? 'success' : 'muted' }}">
                        {{ number_format($role['active_count'], 0, ',', '.') }} aktif
                    </x-admin.status-badge>
                </div>

                <div>
                    <h3>{{ $role['label'] }}</h3>
                    <p class="admin-role-desc">{{ $role['description'] }}</p>
                </div>

                <div class="admin-role-meta">
                    <span><strong>Cakupan:</strong> {{ $role['scope'] }}</span>
                    <span><strong>Total akun:</strong> {{ number_format($role['total_count'], 0, ',', '.') }}</span>
                </div>

                <div>
                    <p class="admin-stat-label">Modul yang dapat diakses:</p>
                    <div class="admin-module-list">
                        @foreach ($role['modules'] as $module)
                            <span class="admin-module-chip">{{ $module }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="admin-role-footer">
                    <a class="admin-relation-link" href="{{ $role['users_url'] }}">
                        <span>Lihat pengguna ({{ number_format($role['total_count'], 0, ',', '.') }})</span>
                        <x-icon name="chevron-right" size="0.95em" />
                    </a>
                    <a class="admin-btn-outline admin-btn-sm" href="{{ $role['create_url'] }}">Tambah akun</a>
                </div>
            </article>
        @endforeach
    </div>
</section>
@endsection
