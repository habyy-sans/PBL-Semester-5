@extends('layouts.app')

@section('title', 'Inventory / Stok')
@section('description', 'Pantau kondisi stok bahan dan produk.')

@section('content')

<section class="page active">

    <div class="page-card">

        <div class="page-card-header">
            <div>
                <h3>Inventory / Stok</h3>
                <p>Monitoring stok bahan dan produk.</p>
            </div>

            <button class="primary-button">+ Tambah Barang</button>
        </div>

        <div class="table-wrapper">
            <table>

                <thead>
                    <tr>
                        <th>Nama Barang</th>
                        <th>Stok</th>
                        <th>Minimum</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>Fresh Milk</td>
                        <td>8 Liter</td>
                        <td>15 Liter</td>
                        <td><span class="status danger">Menipis</span></td>
                    </tr>

                    <tr>
                        <td>Arabica Beans</td>
                        <td>24 Kg</td>
                        <td>10 Kg</td>
                        <td><span class="status success">Aman</span></td>
                    </tr>

                    <tr>
                        <td>Sirup Vanilla</td>
                        <td>5 Botol</td>
                        <td>8 Botol</td>
                        <td><span class="status warning">Menipis</span></td>
                    </tr>
                </tbody>

            </table>
        </div>

    </div>

</section>

@endsection