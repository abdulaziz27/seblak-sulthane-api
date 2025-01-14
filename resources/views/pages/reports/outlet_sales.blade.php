@extends('layouts.app')

@section('title', 'Laporan Penjualan per Outlet')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush


@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>Laporan Penjualan per Outlet</h1>
        </div>

        <div class="section-body">
            <div class="card">
                <div class="card-header">
                    <h4>Periode: {{ $startDate }} - {{ $endDate }}</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Outlet</th>
                                    <th>Total Penjualan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($outletSales as $sale)
                                    <tr>
                                        <td>{{ $sale->outlet_name }}</td>
                                        <td>{{ $sale->total_sales }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
