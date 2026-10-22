@extends('layouts.app')

@section('title', 'Role & Hak Akses')
@section('description', 'Role sistem yang dapat diberikan kepada pengguna.')

@section('content')
<section class="page active admin-panel">
    <div class="admin-page-heading">
        <div>
            <h1>Role & Hak Akses</h1>
            <p>Role disimpan pada kolom pengguna.role sesuai daftar baku aplikasi.</p>
        </div>
        <a class="primary-button" href="{{ route('admin.pengguna.create') }}">+ Tetapkan Role</a>
    </div>
    <div class="admin-role-grid">
        @foreach (config('menu.roles') as $key => $role)
            <article class="page-card admin-role-card">
                <span class="status info">{{ $key }}</span>
                <h2>{{ $role['label'] }}</h2>
                <p>Nilai database: <strong>{{ $role['db'] }}</strong></p>
                <a href="{{ route('admin.pengguna.index', ['role' => $key]) }}">Lihat pengguna dengan role ini →</a>
            </article>
        @endforeach
    </div>
</section>
@endsection
