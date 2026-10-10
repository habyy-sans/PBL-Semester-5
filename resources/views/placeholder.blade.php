@extends('layouts.app')

@section('title', $title)
@section('description', $description)

@section('content')

<section class="page active">

    <div class="page-card">

        <div class="page-card-header">
            <div>
                <h3>{{ $title }}</h3>
                <p>{{ $description }}</p>
            </div>
        </div>

        <div class="cart-empty">
            Halaman ini belum dibuat. Segera hadir.
        </div>

    </div>

</section>

@endsection