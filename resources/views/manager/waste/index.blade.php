@extends('layouts.app')
 
@section('title', 'Waste Log')
@section('description', 'Kelola pencatatan barang rusak atau terbuang.')
 
@section('content')
 
<section class="page active">
 
    <div class="page-card">
 
        <div class="page-toolbar">
            <button class="primary-button">+ Tambah Waste</button>
        </div>
 
        <div class="table-wrapper">
            <table>
 
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th>Jumlah</th>
                        <th>Alasan</th>
                        <th>Nilai</th>
                    </tr>
                </thead>
 
                <tbody>
                    <tr>
                        <td>Sirup Vanilla</td>
                        <td>2 Botol</td>
                        <td>Rusak</td>
                        <td>Rp 85.000</td>
                    </tr>
 
                    <tr>
                        <td>Fresh Milk</td>
                        <td>3 Liter</td>
                        <td>Kedaluwarsa</td>
                        <td>Rp 100.000</td>
                    </tr>
                </tbody>
 
            </table>
        </div>
 
    </div>
 
</section>
 
@endsection
 