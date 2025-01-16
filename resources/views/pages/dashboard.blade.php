@extends('layouts.app')

@section('title', 'General Dashboard')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/jqvmap/dist/jqvmap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/summernote/dist/summernote-bs4.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Dashboard - Seblak Sulthane</h1>
            </div>

            <!-- Filter Date Range -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form action="{{ route('home') }}" method="GET" class="form-inline">
                                <div class="form-group mr-3">
                                    <label class="mr-2">Periode:</label>
                                    <input type="date" class="form-control" name="start_date" value="{{ request('start_date', now()->startOfMonth()->format('Y-m-d')) }}">
                                    <span class="mx-2">s/d</span>
                                    <input type="date" class="form-control" name="end_date" value="{{ request('end_date', now()->format('Y-m-d')) }}">
                                </div>
                                <button type="submit" class="btn btn-primary">Terapkan</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-primary">
                            <i class="fas fa-money-bill"></i>
                        </div>
                        <div class="card-wrap">
                            <div class="card-header">
                                <h4>Total Revenue</h4>
                            </div>
                            <div class="card-body">
                                Rp {{ number_format($totalRevenue, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-danger">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="card-wrap">
                            <div class="card-header">
                                <h4>Total Orders</h4>
                            </div>
                            <div class="card-body">
                                {{ number_format($totalOrders) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-warning">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="card-wrap">
                            <div class="card-header">
                                <h4>Total Members</h4>
                            </div>
                            <div class="card-body">
                                {{ number_format($totalMembers) }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-success">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="card-wrap">
                            <div class="card-header">
                                <h4>Total Staff</h4>
                            </div>
                            <div class="card-body">
                                {{ number_format($totalStaff) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Chart -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Trend Penjualan Harian</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outlet Performance -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Performa Outlet</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Outlet</th>
                                            <th>Total Orders</th>
                                            <th>Total Revenue</th>
                                            <th>Total Customers</th>
                                            <th>Average Order Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($outletPerformance as $outlet)
                                            <tr>
                                                <td>{{ $outlet->outlet_name }}</td>
                                                <td>{{ number_format($outlet->total_orders) }}</td>
                                                <td>Rp {{ number_format($outlet->total_revenue, 0, ',', '.') }}</td>
                                                <td>{{ number_format($outlet->total_customers) }}</td>
                                                <td>Rp {{ $outlet->total_orders > 0 ? number_format($outlet->total_revenue / $outlet->total_orders, 0, ',', '.') : 0 }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <!-- JS Libraries -->
    <script src="{{ asset('library/chart.js/dist/Chart.min.js') }}"></script>

    <script>
        // Sales Chart
        var salesCtx = document.getElementById('salesChart').getContext('2d');
        var salesData = @json($dailySales);

        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.map(item => item.date),
                datasets: [{
                    label: 'Daily Sales',
                    data: salesData.map(item => item.total_sales),
                    borderColor: '#6777ef',
                    backgroundColor: 'rgba(103, 119, 239, 0.2)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            return 'Rp ' + tooltipItem.yLabel.toLocaleString('id-ID');
                        }
                    }
                }
            }
        });
    </script>
@endpush
