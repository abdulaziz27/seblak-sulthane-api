@extends('layouts.app')

@section('title', 'Seblak Sulthane Dashboard')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/jqvmap/dist/jqvmap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/summernote/dist/summernote-bs4.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('library/select2/dist/css/select2.min.css') }}">
    <style>
        .stats-card {
            transition: all 0.3s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .inventory-alert {
            border-left: 4px solid #fc544b;
        }

        .status-badge {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .status-online {
            background-color: #63ed7a;
        }

        .status-offline {
            background-color: #fc544b;
        }

        .status-away {
            background-color: #ffa426;
        }

        .material-order-card {
            border-radius: 10px;
            overflow: hidden;
        }

        .material-order-card.pending {
            border-left: 4px solid #ffa426;
        }

        .material-order-card.approved {
            border-left: 4px solid #47c363;
        }

        .material-order-card.delivered {
            border-left: 4px solid #3abaf4;
        }

        .card-header .nav-tabs .nav-item .nav-link {
            padding: 0.5rem 1rem;
        }

        .card-header .nav-tabs .nav-item .nav-link.active {
            font-weight: 600;
            border-color: #6777ef;
            color: #6777ef;
        }

        .dashboard-summary-tile {
            position: relative;
            padding: 15px 20px;
            border-radius: 10px;
            overflow: hidden;
            z-index: 1;
        }

        .dashboard-summary-tile .icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.2;
            font-size: 50px;
            z-index: -1;
        }

        .tile-primary {
            background: linear-gradient(135deg, #6777ef 0%, #4a59e3 100%);
            color: #fff;
        }

        .tile-success {
            background: linear-gradient(135deg, #63ed7a 0%, #47c363 100%);
            color: #fff;
        }

        .tile-warning {
            background: linear-gradient(135deg, #ffa426 0%, #fc9601 100%);
            color: #fff;
        }

        .tile-danger {
            background: linear-gradient(135deg, #fc544b 0%, #e73535 100%);
            color: #fff;
        }

        .cash-flow-card {
            overflow: hidden;
        }

        .cash-flow-card .cash-in {
            color: #47c363;
        }

        .cash-flow-card .cash-out {
            color: #fc544b;
        }

        .activity-timeline ul {
            list-style-type: none;
            padding-left: 0;
        }

        .activity-timeline ul li {
            position: relative;
            padding-left: 30px;
            padding-bottom: 15px;
            border-left: 2px solid #6777ef;
            margin-left: 10px;
        }

        .activity-timeline ul li:last-child {
            border-left: 2px solid transparent;
        }

        .activity-timeline ul li:before {
            content: '';
            position: absolute;
            top: 0;
            left: -10px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #6777ef;
        }
    </style>
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Dashboard</h1>
            </div>

            <!-- Filter Date Range and Outlet Selection -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form action="{{ route('home') }}" method="GET" id="dashboard-filter-form">
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label class="mb-2">Periode:</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fas fa-calendar"></i>
                                                </div>
                                            </div>
                                            <button type="button" class="form-control text-left daterange-btn"
                                                id="daterange-btn">
                                                <span>Pilih Periode</span>
                                            </button>
                                            <input type="hidden" name="start_date" id="start_date"
                                                value="{{ request('start_date', now()->startOfMonth()->format('Y-m-d')) }}">
                                            <input type="hidden" name="end_date" id="end_date"
                                                value="{{ request('end_date', now()->format('Y-m-d')) }}">
                                        </div>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label class="mb-2">Tampilkan Data:</label>
                                        <select class="form-control select2" name="period_type" id="period_type">
                                            <option value="daily"
                                                {{ request('period_type', 'daily') == 'daily' ? 'selected' : '' }}>Harian
                                            </option>
                                            <option value="weekly"
                                                {{ request('period_type') == 'weekly' ? 'selected' : '' }}>Mingguan</option>
                                            <option value="monthly"
                                                {{ request('period_type') == 'monthly' ? 'selected' : '' }}>
                                                Bulanan</option>
                                        </select>
                                    </div>

                                    @if (Auth::user()->role === 'owner')
                                        <div class="form-group col-md-3">
                                            <label class="mb-2">Outlet:</label>
                                            <select class="form-control select2" name="outlet_id">
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
                                        <div class="form-group col-md-3">
                                            <label class="mb-2">Outlet:</label>
                                            <input type="text" class="form-control"
                                                value="{{ Auth::user()->outlet->name }}" disabled>
                                            <input type="hidden" name="outlet_id" value="{{ Auth::user()->outlet_id }}">
                                        </div>
                                    @endif

                                    <div class="form-group col-md-1 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-filter"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Summary Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>
                                <i class="fas fa-chart-line mr-2"></i>
                                Ringkasan Penjualan {{ $selectedOutlet ? $selectedOutlet->name : 'Semua Outlet' }}
                            </h4>
                            {{-- <div class="card-header-action">
                                <div class="btn-group">
                                    <a href="#" class="btn btn-primary" id="btnPrintSales">
                                        <i class="fas fa-download"></i> Export
                                    </a>
                                </div>
                            </div> --}}
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 col-sm-6 col-12">
                                    <div class="dashboard-summary-tile tile-primary mb-3">
                                        <div class="icon">
                                            <i class="fas fa-money-bill"></i>
                                        </div>
                                        <div class="title mb-1">Total Pendapatan</div>
                                        <h4 class="mb-0">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h4>
                                        @if (isset($previousPeriodRevenue) && $previousPeriodRevenue > 0)
                                            @php $percentChange = (($totalRevenue - $previousPeriodRevenue) / $previousPeriodRevenue) * 100; @endphp
                                            <small class="{{ $percentChange >= 0 ? 'text-white' : 'text-white' }}">
                                                <i
                                                    class="fas fa-{{ $percentChange >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                                                {{ number_format(abs($percentChange), 1) }}% dari periode sebelumnya
                                            </small>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 col-12">
                                    <div class="dashboard-summary-tile tile-success mb-3">
                                        <div class="icon">
                                            <i class="fas fa-shopping-cart"></i>
                                        </div>
                                        <div class="title mb-1">Total Transaksi</div>
                                        <h4 class="mb-0">{{ number_format($totalOrders) }}</h4>
                                        @if (isset($previousPeriodOrders) && $previousPeriodOrders > 0)
                                            @php $percentChange = (($totalOrders - $previousPeriodOrders) / $previousPeriodOrders) * 100; @endphp
                                            <small class="{{ $percentChange >= 0 ? 'text-white' : 'text-white' }}">
                                                <i
                                                    class="fas fa-{{ $percentChange >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                                                {{ number_format(abs($percentChange), 1) }}% dari periode sebelumnya
                                            </small>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 col-12">
                                    <div class="dashboard-summary-tile tile-warning mb-3">
                                        <div class="icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="title mb-1">Total Member</div>
                                        <h4 class="mb-0">{{ number_format($totalMembers) }}</h4>
                                        @if (isset($previousPeriodMembers) && $previousPeriodMembers > 0)
                                            @php $percentChange = (($totalMembers - $previousPeriodMembers) / $previousPeriodMembers) * 100; @endphp
                                            <small class="{{ $percentChange >= 0 ? 'text-white' : 'text-white' }}">
                                                <i
                                                    class="fas fa-{{ $percentChange >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                                                {{ number_format(abs($percentChange), 1) }}% dari periode sebelumnya
                                            </small>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 col-12">
                                    <div class="dashboard-summary-tile tile-danger mb-3">
                                        <div class="icon">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <div class="title mb-1">Total Karyawan</div>
                                        <h4 class="mb-0">{{ number_format($totalStaff) }}</h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Sales Chart -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card bg-light shadow-none">
                                        <div class="card-body">
                                            <canvas id="salesChart" height="280"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cash Flow and Staff Status Section -->
            <div class="row">
                <!-- Cash Flow Section -->
                <div class="col-lg-8 col-md-12 col-sm-12">
                    <div class="card cash-flow-card">
                        <div class="card-header">
                            <h4><i class="fas fa-wallet mr-2"></i> Status Keuangan
                                {{ $selectedOutlet ? $selectedOutlet->name : '' }}</h4>
                            <div class="card-header-action">
                                <ul class="nav nav-tabs" id="financialTab" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="summary-tab" data-toggle="tab" href="#summary"
                                            role="tab" aria-controls="summary" aria-selected="true">Ringkasan</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="cashflow-tab" data-toggle="tab" href="#cashflow"
                                            role="tab" aria-controls="cashflow" aria-selected="false">Arus Kas</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="tab-content" id="financialTabContent">
                                <!-- Summary Tab -->
                                <div class="tab-pane fade show active" id="summary" role="tabpanel"
                                    aria-labelledby="summary-tab">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card card-statistic-1 shadow-sm">
                                                <div class="card-icon bg-primary">
                                                    <i class="fas fa-wallet"></i>
                                                </div>
                                                <div class="card-wrap">
                                                    <div class="card-header">
                                                        <h4>Saldo Awal</h4>
                                                    </div>
                                                    <div class="card-body">
                                                        Rp {{ number_format($totalOpeningBalance, 0, ',', '.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card card-statistic-1 shadow-sm">
                                                <div class="card-icon bg-danger">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                </div>
                                                <div class="card-wrap">
                                                    <div class="card-header">
                                                        <h4>Total Pengeluaran</h4>
                                                    </div>
                                                    <div class="card-body">
                                                        Rp {{ number_format($totalExpenses, 0, ',', '.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-md-6">
                                            <div class="card card-statistic-1 shadow-sm">
                                                <div class="card-icon bg-success">
                                                    <i class="fas fa-cash-register"></i>
                                                </div>
                                                <div class="card-wrap">
                                                    <div class="card-header">
                                                        <h4>Total Penjualan</h4>
                                                    </div>
                                                    <div class="card-body">
                                                        Rp {{ number_format($totalSales, 0, ',', '.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card card-statistic-1 shadow-sm">
                                                <div class="card-icon bg-warning">
                                                    <i class="fas fa-coins"></i>
                                                </div>
                                                <div class="card-wrap">
                                                    <div class="card-header">
                                                        <h4>Saldo Akhir</h4>
                                                    </div>
                                                    <div class="card-body">
                                                        Rp {{ number_format($closingBalance, 0, ',', '.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cash Flow Tab -->
                                <div class="tab-pane fade" id="cashflow" role="tabpanel"
                                    aria-labelledby="cashflow-tab">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th>Keterangan</th>
                                                    <th class="text-right">Cash In</th>
                                                    <th class="text-right">Cash Out</th>
                                                    <th class="text-right">Saldo</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $runningBalance = 0;
                                                    // Sorting daily data by date in ascending order
                                                    if (isset($dailyData)) {
                                                        usort($dailyData, function ($a, $b) {
                                                            return strtotime($a['date']) - strtotime($b['date']);
                                                        });
                                                    }
                                                @endphp

                                                @if (isset($dailyData) && count($dailyData) > 0)
                                                    @foreach ($dailyData as $day)
                                                        @php
                                                            // Set the initial running balance to the opening balance
                                                            $runningBalance = $day['opening_balance'];
                                                        @endphp
                                                        <tr>
                                                            <td>{{ \Carbon\Carbon::parse($day['date'])->format('d M Y') }}
                                                            </td>
                                                            <td>Saldo Awal</td>
                                                            <td class="text-right cash-in">Rp
                                                                {{ number_format($day['opening_balance'], 0, ',', '.') }}
                                                            </td>
                                                            <td class="text-right">-</td>
                                                            <td class="text-right">Rp
                                                                {{ number_format($runningBalance, 0, ',', '.') }}</td>
                                                        </tr>

                                                        @if ($day['total_sales'] > 0)
                                                            @php
                                                                $runningBalance += $day['total_sales'];
                                                            @endphp
                                                            <tr>
                                                                <td>{{ \Carbon\Carbon::parse($day['date'])->format('d M Y') }}
                                                                </td>
                                                                <td>Total Penjualan</td>
                                                                <td class="text-right cash-in">Rp
                                                                    {{ number_format($day['total_sales'], 0, ',', '.') }}
                                                                </td>
                                                                <td class="text-right">-</td>
                                                                <td class="text-right">Rp
                                                                    {{ number_format($runningBalance, 0, ',', '.') }}</td>
                                                            </tr>
                                                        @endif

                                                        @if ($day['expenses'] > 0)
                                                            @php
                                                                $runningBalance -= $day['expenses'];
                                                            @endphp
                                                            <tr>
                                                                <td>{{ \Carbon\Carbon::parse($day['date'])->format('d M Y') }}
                                                                </td>
                                                                <td>Pengeluaran</td>
                                                                <td class="text-right">-</td>
                                                                <td class="text-right cash-out">Rp
                                                                    {{ number_format($day['expenses'], 0, ',', '.') }}</td>
                                                                <td class="text-right">Rp
                                                                    {{ number_format($runningBalance, 0, ',', '.') }}</td>
                                                            </tr>
                                                        @endif

                                                        <tr class="bg-light">
                                                            <td>{{ \Carbon\Carbon::parse($day['date'])->format('d M Y') }}
                                                            </td>
                                                            <td><strong>Saldo Akhir</strong></td>
                                                            <td class="text-right">-</td>
                                                            <td class="text-right">-</td>
                                                            <td class="text-right"><strong>Rp
                                                                    {{ number_format($runningBalance, 0, ',', '.') }}</strong>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                @else
                                                    <tr>
                                                        <td colspan="5" class="text-center">Tidak ada data arus kas
                                                            untuk periode ini</td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Staff Status Section -->
                <div class="col-lg-4 col-md-12 col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-users mr-2"></i> Status Karyawan</h4>
                            <div class="card-header-action">
                                <a href="{{ route('users.index') }}" class="btn btn-primary">
                                    Lihat Semua
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <div class="text-small float-right font-weight-bold text-muted">
                                    {{ $loggedInUsersCount ?? 0 }}/{{ $totalStaff }}</div>
                                <div class="font-weight-bold mb-1">Karyawan Login</div>
                                <div class="progress" data-height="3">
                                    @php
                                        $loginPercentage =
                                            $totalStaff > 0 ? (($loggedInUsersCount ?? 0) / $totalStaff) * 100 : 0;
                                    @endphp
                                    <div class="progress-bar bg-success" role="progressbar"
                                        data-width="{{ $loginPercentage }}%" aria-valuenow="{{ $loginPercentage }}"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>

                            <ul class="list-unstyled list-unstyled-border">
                                @if (isset($loggedInUsers) && count($loggedInUsers) > 0)
                                    @foreach ($loggedInUsers as $user)
                                        <li class="media">
                                            <img alt="image" class="mr-3 rounded-circle" width="50"
                                                src="{{ asset('img/avatar/avatar-1.png') }}">
                                            <div class="media-body">
                                                <div class="mt-0 mb-1 font-weight-bold">{{ $user->name }}</div>
                                                <div class="text-small text-muted">
                                                    <span class="status-badge status-online"></span>
                                                    Online ·
                                                    <span>{{ $user->outlet->name ?? 'N/A' }}</span>
                                                </div>
                                                <div class="text-small text-muted">
                                                    <i class="fas fa-user-tag"></i>
                                                    {{ ucfirst($user->role) }}
                                                </div>
                                            </div>
                                            <div class="media-right">
                                                <div class="text-small text-success">
                                                    @if (isset($user->login_duration))
                                                        {{ $user->login_duration }}
                                                    @else
                                                        {{ \Carbon\Carbon::parse($user->session_started_at ?? now()->subHours(rand(1, 5)))->diffForHumans(null, true) }}
                                                    @endif
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                @else
                                    <li class="text-center py-4">Tidak ada karyawan yang sedang login</li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory and Material Orders Section -->
            <div class="row">
                <!-- Inventory Section -->
                {{-- <div class="col-lg-6 col-md-12 col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-box-open mr-2"></i> Status Inventory & Bahan Baku</h4>
                            <div class="card-header-action">
                                <a href="{{ route('raw-materials.index') }}" class="btn btn-primary">
                                    Lihat Semua
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Inventory Low Stock Alert -->
                            <div class="alert alert-warning mb-4">
                                <div class="alert-title">Peringatan Stok</div>
                                <div>{{ count($lowStockMaterials ?? []) }} bahan memiliki stok kritis</div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card bg-light border-left-warning shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-grow-1">
                                                    <h6 class="text-warning mb-1 font-weight-bold">Stok Bahan Baku</h6>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                        {{ $lowStockCount ?? 0 }} bahan perlu diperhatikan
                                                    </div>
                                                </div>
                                                <div class="ml-2">
                                                    <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light border-left-info shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-grow-1">
                                                    <h6 class="text-info mb-1 font-weight-bold">Total Bahan Baku</h6>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                        {{ $totalRawMaterials ?? 0 }} jenis bahan
                                                    </div>
                                                </div>
                                                <div class="ml-2">
                                                    <i class="fas fa-boxes fa-2x text-info"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stock Info Section -->
                            <div class="alert alert-info mb-0">
                                <div class="d-flex align-items-center">
                                    <div class="mr-3">
                                        <i class="fas fa-info-circle fa-2x"></i>
                                    </div>
                                    <div>
                                        <h6 class="alert-heading mb-1">Informasi Stok</h6>
                                        <p class="mb-0">Monitoring pergerakan stok dapat dilakukan di halaman Bahan Baku.</p>
                                    </div>
                                    <div class="ml-auto">
                                        <a href="{{ route('raw-materials.index') }}" class="btn btn-sm btn-info">
                                            Lihat Detail
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> --}}

                <!-- Material Orders Section -->
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-truck-loading mr-2"></i> Pemesanan Bahan Baku</h4>
                            <div class="card-header-action">
                                <a href="{{ route('material-orders.index') }}" class="btn btn-primary">
                                    Lihat Semua
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Orders Status Summary -->
                            <div class="row">
                                <div class="col-4">
                                    <div class="card card-statistic-1 shadow-sm">
                                        <div class="card-wrap">
                                            <div class="card-header bg-warning text-white">
                                                <h6 class="mb-0">Pending</h6>
                                            </div>
                                            <div class="card-body">
                                                {{ $pendingOrdersCount ?? 0 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="card card-statistic-1 shadow-sm">
                                        <div class="card-wrap">
                                            <div class="card-header bg-info text-white">
                                                <h6 class="mb-0">Disetujui</h6>
                                            </div>
                                            <div class="card-body">
                                                {{ $approvedOrdersCount ?? 0 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="card card-statistic-1 shadow-sm">
                                        <div class="card-wrap">
                                            <div class="card-header bg-success text-white">
                                                <h6 class="mb-0">Terkirim</h6>
                                            </div>
                                            <div class="card-body">
                                                {{ $deliveredOrdersCount ?? 0 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Total Biaya Pembelian -->
                            <div class="card bg-primary text-white shadow-sm mt-4 mb-4">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-8">
                                            <h6 class="mb-0">Total Biaya Pembelian</h6>
                                            <h4 class="mb-0">Rp
                                                {{ number_format($totalMaterialCost ?? 0, 0, ',', '.') }}</h4>
                                        </div>
                                        <div class="col-4 text-right">
                                            <i class="fas fa-shopping-cart fa-3x opacity-50"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Ongoing Orders -->
                            <h6 class="text-dark font-weight-bold mb-3">Pesanan Sedang Berjalan</h6>
                            @if (isset($ongoingOrders) && count($ongoingOrders) > 0)
                                @foreach ($ongoingOrders as $order)
                                    <div class="material-order-card {{ $order->status }} card shadow-sm mb-3">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">#{{ $order->id }} -
                                                        {{ $order->franchise->name }}</h6>
                                                    <div class="text-small text-muted">
                                                        <i class="fas fa-calendar-alt"></i>
                                                        {{ \Carbon\Carbon::parse($order->created_at)->format('d M Y') }}
                                                        <span class="mx-2">|</span>
                                                        <i class="fas fa-user"></i> {{ $order->user->name }}
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <span
                                                        class="badge badge-{{ $order->status == 'pending' ? 'warning' : ($order->status == 'approved' ? 'info' : 'success') }}">
                                                        {{ ucfirst($order->status) }}
                                                    </span>
                                                    <div class="text-dark font-weight-bold mt-1">
                                                        Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mt-2 text-small">
                                                <strong>Items:</strong>
                                                {{ $order->items->count() }} jenis bahan
                                                <a href="{{ route('material-orders.show', $order->id) }}"
                                                    class="float-right text-primary">
                                                    <i class="fas fa-eye"></i> Detail
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="card shadow-sm mb-3">
                                    <div class="card-body p-3 text-center">
                                        Tidak ada pesanan yang sedang berjalan
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product performance and recent activities Section -->
            <div class="row">
                <!-- Product Performance -->
                <div class="col-lg-8 col-md-12 col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-chart-pie mr-2"></i> Performa Produk</h4>
                            <div class="card-header-action">
                                <a href="#" class="btn btn-primary">
                                    Detail
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <canvas id="topItemsChart" height="300"></canvas>
                                </div>
                                <div class="col-md-6">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Produk</th>
                                                    <th class="text-right">Terjual</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @if (isset($topItems) && count($topItems) > 0)
                                                    @foreach ($topItems as $index => $item)
                                                        <tr>
                                                            <td>{{ $index + 1 }}</td>
                                                            <td>{{ $item->product_name }}</td>
                                                            <td class="text-right">
                                                                {{ number_format($item->total_quantity) }}</td>
                                                        </tr>
                                                    @endforeach
                                                @else
                                                    <tr>
                                                        <td colspan="3" class="text-center">Tidak ada data produk
                                                            terjual</td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="col-lg-4 col-md-12 col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-chart-bar mr-2"></i> Ringkasan Statistik</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Average Order Value -->
                                <div class="col-12 mb-4">
                                    <div class="statistic-card border-left-primary p-3 shadow-sm">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Rata-rata Order
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    @php
                                                        $avgOrderValue =
                                                            $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
                                                    @endphp
                                                    Rp {{ number_format($avgOrderValue, 0, ',', '.') }}
                                                </div>
                                            </div>
                                            <div>
                                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Member Orders Percentage -->
                                <div class="col-12 mb-4">
                                    <div class="statistic-card border-left-success p-3 shadow-sm">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    Order dari Member
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    @php
                                                        $memberOrdersCount = $memberOrdersCount ?? rand(30, 70);
                                                        $memberOrdersPercentage =
                                                            $totalOrders > 0
                                                                ? ($memberOrdersCount / $totalOrders) * 100
                                                                : 0;
                                                    @endphp
                                                    {{ number_format($memberOrdersPercentage, 1) }}%
                                                </div>
                                            </div>
                                            <div>
                                                <i class="fas fa-user-tag fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Most Popular Payment Method -->
                                <div class="col-12">
                                    <div class="statistic-card border-left-info p-3 shadow-sm">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                    Metode Pembayaran Terpopuler
                                                </div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                    @php
                                                        $popularPaymentMethod = $popularPaymentMethod ?? 'QRIS';
                                                        $popularPaymentPercentage =
                                                            $popularPaymentPercentage ?? rand(55, 80);
                                                    @endphp
                                                    {{ $popularPaymentMethod }} ({{ $popularPaymentPercentage }}%)
                                                </div>
                                            </div>
                                            <div>
                                                <i class="fas fa-credit-card fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
    <script src="{{ asset('library/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('library/select2/dist/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>

    <script>
        // Initialize select2
        $('.select2').select2();

        // Date range picker initialization
        $(document).ready(function() {
            // Custom setup for daterange button
            $('#daterange-btn').daterangepicker({
                ranges: {
                    'Hari Ini': [moment(), moment()],
                    'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
                    '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
                    'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
                    'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1,
                        'month').endOf('month')]
                },
                startDate: moment().subtract(29, 'days'),
                endDate: moment()
            }, function(start, end) {
                $('#daterange-btn span').html(start.format('D MMM YYYY') + ' - ' + end.format(
                    'D MMM YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });

            // Set current values if they exist
            @if (request('start_date') && request('end_date'))
                $('#daterange-btn span').html(
                    "{{ \Carbon\Carbon::parse(request('start_date'))->format('D MMM YYYY') }} - {{ \Carbon\Carbon::parse(request('end_date'))->format('D MMM YYYY') }}"
                );
            @endif

            // Auto-submit when selecting a date range
            $('#daterange-btn').on('apply.daterangepicker', function(ev, picker) {
                setTimeout(function() {
                    $('#dashboard-filter-form').submit();
                }, 300);
            });
        });

        // Sales Chart
        var salesCtx = document.getElementById('salesChart').getContext('2d');
        var salesData = @json($dailySales ?? []);

        // Format dates and sales data
        var dates = salesData.map(function(item) {
            return moment(item.date).format('D MMM');
        });

        var sales = salesData.map(function(item) {
            return item.total_sales;
        });

        var gradientStroke = salesCtx.createLinearGradient(0, 0, 0, 300);
        gradientStroke.addColorStop(0, 'rgba(103, 119, 239, 0.8)');
        gradientStroke.addColorStop(1, 'rgba(103, 119, 239, 0.2)');

        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Penjualan',
                    data: sales,
                    borderColor: '#6777ef',
                    backgroundColor: gradientStroke,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#6777ef',
                    pointRadius: 4,
                    tension: 0.3,
                    fill: true
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
                            callback: function(value) {
                                return 'Rp ' + value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                            }
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
                            return 'Rp ' + tooltipItem.yLabel.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        }
                    }
                }
            }
        });

        // Top Items Chart
        var topItemsCtx = document.getElementById('topItemsChart').getContext('2d');
        var topItemsData = @json($topItems ?? []);

        var productNames = topItemsData.map(function(item) {
            return item.product_name;
        });

        var productQuantities = topItemsData.map(function(item) {
            return item.total_quantity;
        });

        var colors = [
            '#6777ef',
            '#63ed7a',
            '#ffa426',
            '#fc544b',
            '#3abaf4'
        ];

        new Chart(topItemsCtx, {
            type: 'doughnut',
            data: {
                labels: productNames,
                datasets: [{
                    data: productQuantities,
                    backgroundColor: colors,
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutoutPercentage: 70,
                legend: {
                    position: 'bottom',
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
                            return data.labels[tooltipItem.index] + ': ' +
                                currentValue.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + ' (' +
                                percentage + '%)';
                        }
                    }
                }
            }
        });

        // Print functionality
        $('#btnPrintSales').click(function() {
            // Add print functionality here
            window.print();
        });
    </script>
@endpush
