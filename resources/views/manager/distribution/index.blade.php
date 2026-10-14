@extends('layouts.app')
 
@section('title', 'Distribution Center')
@section('description', 'Pantau proses distribusi barang dari DC.')
 
@section('content')
 
<section class="page active">
 
    <div class="page-card">
 
        <div class="table-wrapper">
            <table>
 
                <thead>
                    <tr>
                        <th>Surat Jalan</th>
                        <th>Cabang</th>
                        <th>Jumlah Item</th>
                        <th>Status</th>
                    </tr>
                </thead>
 
                <tbody>
                    <tr>
                        <td>#SJ-018</td>
                        <td>Malang</td>
                        <td>12 Item</td>
                        <td><span class="status warning">Dalam Pengiriman</span></td>
                    </tr>
 
                    <tr>
                        <td>#SJ-017</td>
                        <td>Surabaya</td>
                        <td>9 Item</td>
                        <td><span class="status success">Selesai</span></td>
                    </tr>
                </tbody>
 
            </table>
        </div>
 
    </div>
 
</section>
 
@endsection
 