@extends('layouts.app')

@section('title', 'Analisis Kinerja Outlet')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>Analisis Kinerja Outlet</h1>
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
                                    <th>Pendapatan</th>
                                    <th>Laba Kotor</th>
                                    <th>Total Pesanan</th>
                                    <th>Total Pelanggan</th>
                                    <th>Rasio Konversi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($outletPerformance as $performance)
                                    <tr>
                                        <td>{{ $performance->outlet_name }}</td>
                                        <td>{{ $performance->revenue }}</td>
                                        <td>{{ $performance->gross_profit }}</td>
                                        <td>{{ $performance->total_orders }}</td>
                                        <td>{{ $performance->total_customers }}</td>
                                        <td>{{ $performance->conversion_rate }}</td>
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
