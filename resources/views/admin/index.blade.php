@extends('layouts.app')

@section('title', 'Admin Sistem')
@section('description', 'Kelola pengguna, produk dan hak akses.')

@section('content')

<section class="page active">

    <div class="page-card">

        <div class="page-card-header">
            <div>
                <h3>Admin Sistem</h3>
                <p>Pengelolaan pengguna, produk dan hak akses.</p>
            </div>

            <button class="primary-button">+ Tambah User</button>
        </div>

        <div class="table-wrapper">
            <table>

                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Role</th>
                        <th>Cabang</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>Manager Cabang</td>
                        <td>Manager</td>
                        <td>Malang</td>
                        <td><span class="status success">Aktif</span></td>
                    </tr>

                    <tr>
                        <td>Kepala Shift</td>
                        <td>IC</td>
                        <td>Malang</td>
                        <td><span class="status success">Aktif</span></td>
                    </tr>

                    <tr>
                        <td>Kasir 01</td>
                        <td>Kasir</td>
                        <td>Malang</td>
                        <td><span class="status success">Aktif</span></td>
                    </tr>
                </tbody>

            </table>
        </div>

    </div>

</section>

@endsection