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
                            <form action="{{ route('home') }}" method="GET" id="dashboard-filter-form">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label class="d-block">Periode:</label>
                                        <button type="button" class="btn btn-primary daterange-btn icon-left btn-icon"
                                            id="daterange-btn">
                                            <i class="fas fa-calendar"></i>
                                            <span>Choose Date Range</span>
                                        </button>
                                        <input type="hidden" name="start_date" id="start_date"
                                            value="{{ request('start_date', now()->startOfMonth()->format('Y-m-d')) }}">
                                        <input type="hidden" name="end_date" id="end_date"
                                            value="{{ request('end_date', now()->format('Y-m-d')) }}">
                                    </div>
                                    @if (Auth::user()->role === 'owner')
                                        <div class="form-group col-md-4">
                                            <label class="mr-2">Outlet:</label>
                                            <select class="form-control selectric" name="outlet_id">
                                                <option value="">Semua Outlet</option>
                                                @foreach ($outlets as $outlet)
                                                    <option value="{{ $outlet->id }}"
                                                        {{ request('outlet_id') == $outlet->id ? 'selected' : '' }}>
                                                        {{ $outlet->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @else
                                        <div class="form-group col-md-5">
                                            <label class="mr-2">Outlet:</label>
                                            <span class="badge badge-info">{{ Auth::user()->outlet->name }}</span>
                                            <input type="hidden" name="outlet_id" value="{{ Auth::user()->outlet_id }}">
                                        </div>
                                    @endif
                                    <div class="form-group col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary btn-lg btn-block">Terapkan</button>
                                    </div>
                                </div>
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

            <!-- Top Selling Items & Customers -->
            <div class="row">
                <!-- Top Selling Items (existing) -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Produk Terlaris</h4>
                            @if (request('outlet_id'))
                                <div class="ml-auto">
                                    <span class="badge badge-primary">{{ $selectedOutlet->name ?? '' }}</span>
                                </div>
                            @elseif(Auth::user()->role === 'owner')
                                <div class="ml-auto">
                                    <span class="badge badge-primary">Semua Outlet</span>
                                </div>
                            @endif
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <canvas id="topItemsChart" height="250"></canvas>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <tr>
                                                <th>Produk</th>
                                                <th>Total Terjual</th>
                                            </tr>
                                            @foreach ($topItems as $item)
                                                <tr>
                                                    <td>{{ $item->product_name }}</td>
                                                    <td>{{ number_format($item->total_quantity) }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Customers (new) -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Pelanggan Terbaik</h4>
                            @if (request('outlet_id'))
                                <div class="ml-auto">
                                    <span class="badge badge-primary">{{ $selectedOutlet->name ?? '' }}</span>
                                </div>
                            @elseif(Auth::user()->role === 'owner')
                                <div class="ml-auto">
                                    <span class="badge badge-primary">Semua Outlet</span>
                                </div>
                            @endif
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Member</th>
                                            <th>No. Telepon</th>
                                            <th class="text-right">Total Transaksi</th>
                                            <th class="text-right">Total Pembelian</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($topCustomers as $customer)
                                            <tr>
                                                <td>{{ $customer->member_name }}</td>
                                                <td>{{ $customer->member_phone }}</td>
                                                <td class="text-right">{{ number_format($customer->total_transactions) }}x
                                                </td>
                                                <td class="text-right">Rp
                                                    {{ number_format($customer->total_spent, 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4">
                                <div class="badges">
                                    @foreach ($topCustomers as $index => $customer)
                                        @php
                                            $colors = ['primary', 'success', 'warning', 'danger', 'info'];
                                            $badges = ['Diamond', 'Gold', 'Silver', 'Bronze', 'Regular'];
                                        @endphp
                                        <div class="badge badge-{{ $colors[$index] }} mt-1">
                                            {{ $badges[$index] }}: {{ $customer->member_name }}
                                        </div><br>
                                    @endforeach
                                </div>
                            </div>
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
                                            <th>Average Order Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (Auth::user()->role === 'owner')
                                            @foreach ($outletPerformance as $outlet)
                                                <tr>
                                                    <td>{{ $outlet->outlet_name }}</td>
                                                    <td>{{ number_format($outlet->total_orders) }}</td>
                                                    <td>Rp {{ number_format($outlet->total_revenue, 0, ',', '.') }}</td>
                                                    <td>Rp
                                                        {{ $outlet->total_orders > 0 ? number_format($outlet->total_revenue / $outlet->total_orders, 0, ',', '.') : 0 }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            @foreach ($outletPerformance as $outlet)
                                                @if ($outlet->outlet_id === Auth::user()->outlet_id)
                                                    <tr>
                                                        <td>{{ $outlet->outlet_name }}</td>
                                                        <td>{{ number_format($outlet->total_orders) }}</td>
                                                        <td>Rp {{ number_format($outlet->total_revenue, 0, ',', '.') }}
                                                        </td>
                                                        <td>Rp
                                                            {{ $outlet->total_orders > 0 ? number_format($outlet->total_revenue / $outlet->total_orders, 0, ',', '.') : 0 }}
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Statistics -->
            <!-- Raw Materials Statistics Section -->
            <div class="row">
                <div class="col-12 col-sm-12 col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Raw Material Orders</h4>
                            <div class="card-header-action">
                                <a href="#rm-weekly" data-tab="rm-period-tab" class="btn active">Week</a>
                                <a href="#rm-monthly" data-tab="rm-period-tab" class="btn">Month</a>
                                <a href="#rm-yearly" data-tab="rm-period-tab" class="btn">Year</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Charts will change based on time period -->
                            <div class="rm-period-tab" id="rm-weekly">
                                <canvas id="materialOrdersWeeklyChart" height="180"></canvas>
                            </div>
                            <div class="rm-period-tab" id="rm-monthly" style="display: none;">
                                <canvas id="materialOrdersMonthlyChart" height="180"></canvas>
                            </div>
                            <div class="rm-period-tab" id="rm-yearly" style="display: none;">
                                <canvas id="materialOrdersYearlyChart" height="180"></canvas>
                            </div>

                            <!-- Summary info and statistics -->
                            <div class="mt-4">
                                <div class="statistic-details mt-1">
                                    <div class="statistic-details-item">
                                        <div class="text-small text-muted">
                                            <span
                                                class="{{ $materialOrdersStats->count() > 0 && isset($materialOrdersWeekChange) && $materialOrdersWeekChange > 0 ? 'text-primary' : 'text-danger' }}">
                                                <i
                                                    class="fas fa-caret-{{ $materialOrdersStats->count() > 0 && isset($materialOrdersWeekChange) && $materialOrdersWeekChange > 0 ? 'up' : 'down' }}"></i>
                                            </span>
                                            {{ $materialOrdersStats->count() > 0 && isset($materialOrdersWeekChange) ? abs($materialOrdersWeekChange) : 0 }}%
                                        </div>
                                        <div class="detail-value">
                                            {{ $materialOrdersStats->count() > 0 ? $materialOrdersStats->sum('total_orders_this_week') : 0 }}
                                        </div>
                                        <div class="detail-name">This Week</div>
                                    </div>
                                    <div class="statistic-details-item">
                                        <div class="text-small text-muted">
                                            <span
                                                class="{{ $materialOrdersStats->count() > 0 && isset($materialOrdersMonthChange) && $materialOrdersMonthChange > 0 ? 'text-primary' : 'text-danger' }}">
                                                <i
                                                    class="fas fa-caret-{{ $materialOrdersStats->count() > 0 && isset($materialOrdersMonthChange) && $materialOrdersMonthChange > 0 ? 'up' : 'down' }}"></i>
                                            </span>
                                            {{ $materialOrdersStats->count() > 0 && isset($materialOrdersMonthChange) ? abs($materialOrdersMonthChange) : 0 }}%
                                        </div>
                                        <div class="detail-value">
                                            {{ $materialOrdersStats->count() > 0 ? $materialOrdersStats->sum('total_orders_this_month') : 0 }}
                                        </div>
                                        <div class="detail-name">This Month</div>
                                    </div>
                                    <div class="statistic-details-item">
                                        <div class="text-small text-muted">
                                            <span
                                                class="{{ $materialOrdersStats->count() > 0 && isset($materialOrdersYearChange) && $materialOrdersYearChange > 0 ? 'text-primary' : 'text-danger' }}">
                                                <i
                                                    class="fas fa-caret-{{ $materialOrdersStats->count() > 0 && isset($materialOrdersYearChange) && $materialOrdersYearChange > 0 ? 'up' : 'down' }}"></i>
                                            </span>
                                            {{ $materialOrdersStats->count() > 0 && isset($materialOrdersYearChange) ? abs($materialOrdersYearChange) : 0 }}%
                                        </div>
                                        <div class="detail-value">
                                            {{ $materialOrdersStats->count() > 0 ? $materialOrdersStats->sum('total_orders_this_year') : 0 }}
                                        </div>
                                        <div class="detail-name">This Year</div>
                                    </div>
                                    <div class="statistic-details-item">
                                        <div class="detail-value">Rp
                                            {{ $materialOrdersStats->count() > 0 ? number_format($materialOrdersStats->sum('total_amount'), 0, ',', '.') : 0 }}
                                        </div>
                                        <div class="detail-name">Total Spending</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-12 col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Top Ordered Materials</h4>
                            @if (request('outlet_id'))
                                <div class="ml-auto">
                                    <span class="badge badge-primary">{{ $selectedOutlet->name ?? '' }}</span>
                                </div>
                            @elseif(Auth::user()->role === 'owner')
                                <div class="ml-auto">
                                    <span class="badge badge-primary">Semua Outlet</span>
                                </div>
                            @endif
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <canvas id="topMaterialsChart" height="250"></canvas>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Material</th>
                                            <th>Total Quantity</th>
                                            <th class="text-right">Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($topMaterials as $material)
                                            <tr>
                                                <td>{{ $material->material_name }}</td>
                                                <td>{{ number_format($material->total_quantity) }} {{ $material->unit }}
                                                </td>
                                                <td class="text-right">Rp
                                                    {{ number_format($material->total_amount, 0, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Material Orders by Outlet -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Material Orders by Outlet</h4>
                            <div class="card-header-action">
                                <div class="btn-group">
                                    <a href="#" class="btn btn-primary">Export Data</a>
                                    <a href="#" class="btn">Print</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <canvas id="outletMaterialPieChart" height="300"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Outlet</th>
                                                    <th class="text-center">Total Orders</th>
                                                    <th class="text-right">Total Spending</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($materialOrdersStats as $stat)
                                                    <tr>
                                                        <td>{{ $stat->outlet_name }}</td>
                                                        <td class="text-center">{{ number_format($stat->total_orders) }}
                                                        </td>
                                                        <td class="text-right">Rp
                                                            {{ number_format($stat->total_amount, 0, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th>Total</th>
                                                    <th class="text-center">
                                                        {{ number_format($materialOrdersStats->sum('total_orders')) }}</th>
                                                    <th class="text-right">Rp
                                                        {{ number_format($materialOrdersStats->sum('total_amount'), 0, ',', '.') }}
                                                    </th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            @foreach ($materialOrdersStats as $outlet)
                                <div class="mb-4 mt-4">
                                    <div class="text-small font-weight-bold text-muted float-right">Rp
                                        {{ number_format($outlet->total_amount, 0, ',', '.') }}</div>
                                    <div class="font-weight-bold mb-1">{{ $outlet->outlet_name }}</div>
                                    <div class="progress" data-height="5">
                                        <div class="progress-bar" role="progressbar"
                                            data-width="{{ ($outlet->total_amount / ($materialOrdersStats->sum('total_amount') ?: 1)) * 100 }}%"
                                            aria-valuenow="{{ ($outlet->total_amount / ($materialOrdersStats->sum('total_amount') ?: 1)) * 100 }}"
                                            aria-valuemin="0" aria-valuemax="100"
                                            style="background-color: {{ $loop->index % 2 == 0 ? '#6777ef' : '#63ed7a' }}">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/jqvmap/dist/jqvmap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/summernote/dist/summernote-bs4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush


@push('scripts')
    <!-- JS Libraries -->
    <script src="{{ asset('library/chart.js/dist/Chart.min.js') }}"></script>
    <script src="{{ asset('library/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>

    <script>
        // Date range picker initialization
        $(document).ready(function() {
            // Initialize selectric
            $('.selectric').selectric();

            // Custom setup for daterange button
            $('#daterange-btn').daterangepicker({
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1,
                        'month').endOf('month')]
                },
                startDate: moment().subtract(29, 'days'),
                endDate: moment()
            }, function(start, end) {
                $('#daterange-btn span').html(start.format('MMMM D, YYYY') + ' - ' + end.format(
                    'MMMM D, YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            // Set current values if they exist
            @if (request('start_date') && request('end_date'))
                $('#daterange-btn span').html(
                    "{{ \Carbon\Carbon::parse(request('start_date'))->format('MMMM D, YYYY') }} - {{ \Carbon\Carbon::parse(request('end_date'))->format('MMMM D, YYYY') }}"
                    );
            @endif

            // Auto-submit when selecting a date range
            $('#daterange-btn').on('apply.daterangepicker', function(ev, picker) {
                setTimeout(function() {
                    $('#filter-form').submit();
                }, 300);
            });

            // Confirm delete functionality
            $('.confirm-delete').click(function(e) {
                var form = $(this).closest('form');
                e.preventDefault();

                swal({
                    title: 'Are you sure?',
                    text: 'Once cancelled, you will not be able to recover this order!',
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true,
                }).then((willDelete) => {
                    if (willDelete) {
                        form.submit();
                    }
                });
            });
        });

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

    <script>
        // Add this to your existing dashboard JavaScript code
        $(document).ready(function() {
            // Tab switching functionality
            $("[data-tab]").each(function() {
                let tab_group = $(this).data("tab");
                $(this).click(function(e) {
                    e.preventDefault();

                    // Remove active class from all buttons in this group
                    $("[data-tab='" + tab_group + "']").removeClass("active");

                    // Add active class to this button
                    $(this).addClass("active");

                    // Hide all content with this data-tab-group
                    $("[data-tab-group='" + tab_group + "']").hide();

                    // Show the content this button points to
                    $($(this).attr("href")).show();
                });
            });

            // Material Orders Weekly Chart
            var materialOrdersWeeklyCtx = document.getElementById('materialOrdersWeeklyChart').getContext('2d');
            var weeklyOrderData = @json($weeklyOrderData);

            new Chart(materialOrdersWeeklyCtx, {
                type: 'line',
                data: {
                    labels: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                    datasets: [{
                        label: 'Material Orders',
                        data: weeklyOrderData,
                        backgroundColor: 'rgba(103, 119, 239, 0.2)',
                        borderColor: '#6777ef',
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#6777ef',
                        pointRadius: 5,
                        pointHoverRadius: 8,
                        borderWidth: 2,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        display: false
                    },
                    scales: {
                        yAxes: [{
                            gridLines: {
                                drawBorder: false,
                                color: '#f2f2f2',
                            },
                            ticks: {
                                beginAtZero: true,
                                stepSize: 1
                            }
                        }],
                        xAxes: [{
                            gridLines: {
                                display: false
                            }
                        }]
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                return tooltipItem.yLabel + ' orders';
                            }
                        }
                    }
                }
            });

            // Material Orders Monthly Chart
            var materialOrdersMonthlyCtx = document.getElementById('materialOrdersMonthlyChart').getContext('2d');
            var monthlyOrderData = @json($monthlyOrderData);
            var monthLabels = @json($monthLabels);

            new Chart(materialOrdersMonthlyCtx, {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Material Orders',
                        data: monthlyOrderData,
                        backgroundColor: '#6777ef',
                        borderWidth: 0,
                        borderRadius: 4,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        display: false
                    },
                    scales: {
                        yAxes: [{
                            gridLines: {
                                drawBorder: false,
                                color: '#f2f2f2',
                            },
                            ticks: {
                                beginAtZero: true,
                                stepSize: 5
                            }
                        }],
                        xAxes: [{
                            gridLines: {
                                display: false
                            }
                        }]
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                return tooltipItem.yLabel + ' orders';
                            }
                        }
                    }
                }
            });

            // Material Orders Yearly Chart
            var materialOrdersYearlyCtx = document.getElementById('materialOrdersYearlyChart').getContext('2d');
            var yearlyOrderData = @json($yearlyOrderData);
            var yearLabels = @json($yearLabels);

            new Chart(materialOrdersYearlyCtx, {
                type: 'bar',
                data: {
                    labels: yearLabels,
                    datasets: [{
                        label: 'Material Orders',
                        data: yearlyOrderData,
                        backgroundColor: '#63ed7a',
                        borderWidth: 0,
                        borderRadius: 4,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        display: false
                    },
                    scales: {
                        yAxes: [{
                            gridLines: {
                                drawBorder: false,
                                color: '#f2f2f2',
                            },
                            ticks: {
                                beginAtZero: true,
                                stepSize: 10
                            }
                        }],
                        xAxes: [{
                            gridLines: {
                                display: false
                            }
                        }]
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                return tooltipItem.yLabel + ' orders';
                            }
                        }
                    }
                }
            });

            // Top Materials Pie Chart
            var topMaterialsCtx = document.getElementById('topMaterialsChart').getContext('2d');
            var topMaterialsData = @json($topMaterials);

            var materialColors = [
                '#6777ef',
                '#63ed7a',
                '#ffa426',
                '#fc544b',
                '#3abaf4'
            ];

            new Chart(topMaterialsCtx, {
                type: 'doughnut',
                data: {
                    labels: topMaterialsData.map(item => item.material_name),
                    datasets: [{
                        data: topMaterialsData.map(item => item.total_amount),
                        backgroundColor: materialColors,
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutoutPercentage: 70,
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var dataset = data.datasets[0];
                                var total = dataset.data.reduce((acc, current) => acc + current, 0);
                                var currentValue = dataset.data[tooltipItem.index];
                                var percentage = Math.round((currentValue / total * 100));
                                return data.labels[tooltipItem.index] + ': Rp ' +
                                    currentValue.toLocaleString('id-ID') + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            });

            // Progress bar animations
            $('.progress .progress-bar').each(function() {
                $(this).css({
                    width: $(this).attr('data-width') + '%'
                });
            });

            // Outlet Material Pie Chart
            var outletMaterialPieCtx = document.getElementById('outletMaterialPieChart').getContext('2d');
            var outletData = @json($materialOrdersStats);

            // Generate colors for each outlet
            var outletColors = [];
            for (var i = 0; i < outletData.length; i++) {
                if (i % 2 == 0) {
                    outletColors.push('#6777ef');
                } else {
                    outletColors.push('#63ed7a');
                }
            }

            new Chart(outletMaterialPieCtx, {
                type: 'pie',
                data: {
                    labels: outletData.map(item => item.outlet_name),
                    datasets: [{
                        data: outletData.map(item => item.total_amount),
                        backgroundColor: outletColors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 20
                        }
                    },
                    tooltips: {
                        callbacks: {
                            label: function(tooltipItem, data) {
                                var dataset = data.datasets[0];
                                var total = dataset.data.reduce((acc, current) => acc + current, 0);
                                var currentValue = dataset.data[tooltipItem.index];
                                var percentage = Math.round((currentValue / total * 100));
                                return data.labels[tooltipItem.index] + ': Rp ' +
                                    currentValue.toLocaleString('id-ID') + ' (' + percentage + '%)';
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    }
                }
            });

            // Handle Export and Print Buttons
            $('.card-header-action .btn-primary').on('click', function(e) {
                e.preventDefault();
                // You can implement actual export functionality here
                alert('Export feature will be implemented soon!');
            });

            $('.card-header-action .btn:not(.btn-primary)').on('click', function(e) {
                e.preventDefault();
                window.print();
            });

            // Add hover effects on rows
            $('.table-responsive table tbody tr').hover(
                function() {
                    $(this).addClass('bg-light');
                },
                function() {
                    $(this).removeClass('bg-light');
                }
            );

            // Auto-refresh data every 5 minutes (300000 ms)
            // Uncomment this if you want auto-refresh functionality

            setInterval(function() {
                location.reload();
            }, 300000);

        });
    </script>

    <!-- Top Items Pie Chart -->
    <script>
        var topItemsCtx = document.getElementById('topItemsChart').getContext('2d');
        var topItemsData = @json($topItems);

        var colors = [
            '#6777ef',
            '#63ed7a',
            '#ffa426',
            '#fc544b',
            '#3abaf4'
        ];

        new Chart(topItemsCtx, {
            type: 'pie',
            data: {
                labels: topItemsData.map(item => item.product_name),
                datasets: [{
                    data: topItemsData.map(item => item.total_quantity),
                    backgroundColor: colors,
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true,
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 20
                    }
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            var dataset = data.datasets[0];
                            var total = dataset.data.reduce((acc, current) => acc + current, 0);
                            var currentValue = dataset.data[tooltipItem.index];
                            var percentage = Math.round((currentValue / total * 100));
                            return data.labels[tooltipItem.index] + ': ' +
                                currentValue.toLocaleString('id-ID') + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        });
    </script>
@endpush
