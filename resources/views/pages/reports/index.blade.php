@extends('layouts.app')

@section('title', 'Dashboard Laporan')

@push('style')
    <link rel="stylesheet" href="{{ asset('library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('library/select2/dist/css/select2.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Dashboard Laporan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item">Laporan</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Unduh Laporan</h2>
                <p class="section-lead">
                    Pilih jenis laporan dan rentang tanggal, lalu klik tombol unduh untuk mendapatkan laporan dalam format
                    PDF atau Excel.
                </p>

                <div class="row">
                    <!-- Card Laporan Ringkasan Penjualan -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Laporan Ringkasan Penjualan POS</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.sales-summary') }}" method="GET" target="_blank"
                                    id="sales-summary-form">
                                    <div class="form-group">
                                        <label>Rentang Tanggal</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fas fa-calendar"></i>
                                                </div>
                                            </div>
                                            <button type="button" class="form-control text-left daterange-btn"
                                                id="sales-daterange-btn">
                                                <span>Pilih Periode</span>
                                            </button>
                                            <input type="hidden" name="start_date" id="sales_start_date"
                                                value="{{ now()->subDays(29)->format('Y-m-d') }}">
                                            <input type="hidden" name="end_date" id="sales_end_date"
                                                value="{{ now()->format('Y-m-d') }}">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Outlet</label>
                                        <select class="form-control select2" name="outlet_id">
                                            <option value="">
                                                {{ Auth::user()->role === 'owner' ? 'Semua Outlet' : Auth::user()->outlet->name }}
                                            </option>
                                            @if (Auth::user()->role === 'owner')
                                                @foreach ($outlets as $outlet)
                                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Tipe Periode</label>
                                        <select class="form-control" name="period_type">
                                            <option value="daily">Harian</option>
                                            <option value="weekly">Mingguan</option>
                                            <option value="monthly">Bulanan</option>
                                        </select>
                                        <small class="form-text text-muted">Harian: data per hari, Mingguan: data per
                                            minggu, Bulanan: data per bulan</small>
                                    </div>
                                    <div class="form-group">
                                        <label>Format</label>
                                        <select class="form-control" name="format">
                                            <option value="pdf">PDF</option>
                                            <option value="excel">Excel</option>
                                        </select>
                                    </div>
                                    <div class="text-right">
                                        <button type="submit" class="btn btn-primary">Unduh Laporan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Card Laporan Pembelian Bahan Baku -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Laporan Pembelian Bahan Baku</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.material-purchases') }}" method="GET" target="_blank"
                                    id="material-purchases-form">
                                    <div class="form-group">
                                        <label>Rentang Tanggal</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fas fa-calendar"></i>
                                                </div>
                                            </div>
                                            <button type="button" class="form-control text-left daterange-btn"
                                                id="materials-daterange-btn">
                                                <span>Pilih Periode</span>
                                            </button>
                                            <input type="hidden" name="start_date" id="materials_start_date"
                                                value="{{ now()->subDays(29)->format('Y-m-d') }}">
                                            <input type="hidden" name="end_date" id="materials_end_date"
                                                value="{{ now()->format('Y-m-d') }}">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Outlet</label>
                                        <select class="form-control select2" name="outlet_id">
                                            <option value="">
                                                {{ Auth::user()->role === 'owner' ? 'Semua Outlet' : Auth::user()->outlet->name }}
                                            </option>
                                            @if (Auth::user()->role === 'owner')
                                                @foreach ($outlets as $outlet)
                                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Format</label>
                                        <select class="form-control" name="format">
                                            <option value="pdf">PDF</option>
                                            <option value="excel">Excel</option>
                                        </select>
                                    </div>
                                    <div class="text-right">
                                        <button type="submit" class="btn btn-primary">Unduh Laporan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Card Laporan Pembelian Bahan dari Supplier -->
                    @if (Auth::user()->role === 'owner' || Auth::user()->isWarehouseStaff())
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Laporan Pembelian dari Supplier</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.supplier-purchases') }}" method="GET" target="_blank"
                                    id="supplier-purchases-form">
                                    <div class="form-group">
                                        <label>Rentang Tanggal</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fas fa-calendar"></i>
                                                </div>
                                            </div>
                                            <button type="button" class="form-control text-left daterange-btn"
                                                id="supplier-daterange-btn">
                                                <span>Pilih Periode</span>
                                            </button>
                                            <input type="hidden" name="start_date" id="supplier_start_date"
                                                value="{{ now()->subDays(29)->format('Y-m-d') }}">
                                            <input type="hidden" name="end_date" id="supplier_end_date"
                                                value="{{ now()->format('Y-m-d') }}">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Format</label>
                                        <select class="form-control" name="format">
                                            <option value="pdf">PDF</option>
                                            <option value="excel">Excel</option>
                                        </select>
                                    </div>
                                    <div class="text-right">
                                        <button type="submit" class="btn btn-primary">Unduh Laporan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('library/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('library/select2/dist/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>

    <script>
        $(document).ready(function() {
            // Initialize select2
            $('.select2').select2();

            // Initialize daterangepicker for each button
            $('.daterange-btn').each(function() {
                var buttonId = $(this).attr('id');
                var formPrefix = buttonId.split('-')[
                    0]; // Extract prefix (sales, outlet, product, customer)

                $(this).daterangepicker({
                    ranges: {
                        'Hari Ini': [moment(), moment()],
                        'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                        '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
                        '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
                        'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
                        'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment()
                            .subtract(1, 'month').endOf('month')
                        ]
                    },
                    locale: {
                        format: 'YYYY-MM-DD',
                        applyLabel: 'Terapkan',
                        cancelLabel: 'Batal',
                        fromLabel: 'Dari',
                        toLabel: 'Sampai',
                        customRangeLabel: 'Kustom',
                        weekLabel: 'M',
                        daysOfWeek: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
                        monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
                            'Agustus',
                            'September', 'Oktober', 'November', 'Desember'
                        ],
                        firstDay: 1
                    },
                    startDate: moment().subtract(29, 'days'),
                    endDate: moment()
                }, function(start, end) {
                    // Update button text
                    $(this.element).find('span').html(start.format('D MMM YYYY') + ' - ' + end
                        .format('D MMM YYYY'));

                    // Update hidden fields based on form prefix
                    $('#' + formPrefix + '_start_date').val(start.format('YYYY-MM-DD'));
                    $('#' + formPrefix + '_end_date').val(end.format('YYYY-MM-DD'));

                    console.log('Updated dates for ' + formPrefix + ': ' +
                        start.format('YYYY-MM-DD') + ' to ' +
                        end.format('YYYY-MM-DD'));
                });

                // Set initial text on the button
                var start = moment().subtract(29, 'days');
                var end = moment();
                $(this).find('span').html(start.format('D MMM YYYY') + ' - ' + end.format('D MMM YYYY'));
            });

            // Make sure forms submit with proper dates
            $('form').on('submit', function(e) {
                var formId = $(this).attr('id');
                console.log("Submitting form: " + formId);

                if (formId) {
                    // Get the prefix from the form ID
                    var prefix = formId.split('-')[0];

                    console.log("start_date: " + $('#' + prefix + '_start_date').val());
                    console.log("end_date: " + $('#' + prefix + '_end_date').val());
                }
            });
        });
    </script>
@endpush
