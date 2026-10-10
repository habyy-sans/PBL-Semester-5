@extends('layouts.app')

@section('title', 'Restock')
@section('description', 'Kelola draft restock dan approval.')

@section('content')

<section class="page active">

    <div class="page-card">

        <div class="page-card-header">
            <div>
                <h3>Restock</h3>
                <p>Kelola draft restock dan approval.</p>
            </div>
        </div>

        <div class="table-wrapper">
            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Jumlah Item</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>#RS-024</td>
                        <td>30 Sep 2026</td>
                        <td>8 Item</td>
                        <td><span class="status warning">Menunggu Approval</span></td>
                    </tr>

                    <tr>
                        <td>#RS-023</td>
                        <td>29 Sep 2026</td>
                        <td>5 Item</td>
                        <td><span class="status success">Disetujui</span></td>
                    </tr>
                </tbody>

            </table>
        </div>

    </div>

</section>

@endsection