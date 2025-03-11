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
                    Pilih jenis laporan dan rentang tanggal, lalu klik tombol unduh untuk mendapatkan laporan dalam format PDF atau Excel.
                </p>

                <div class="row">
                    <!-- Kartu Laporan Ringkasan Penjualan -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Laporan Ringkasan Penjualan</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.sales-summary') }}" method="GET" target="_blank">
                                    <div class="form-group">
                                        <label>Rentang Tanggal</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fas fa-calendar"></i>
                                                </div>
                                            </div>
                                            <input type="text" class="form-control daterange" name="date_range">
                                            <input type="hidden" name="start_date" id="sales_start_date">
                                            <input type="hidden" name="end_date" id="sales_end_date">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Outlet</label>
                                        <select class="form-control select2" name="outlet_id">
                                            <option value="">Semua Outlet</option>
                                            @foreach($outlets as $outlet)
                                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                            @endforeach
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

                    <!-- Kartu Laporan Kinerja Outlet -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Laporan Kinerja Outlet</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.outlet-performance') }}" method="GET" target="_blank">
                                    <div class="form-group">
                                        <label>Rentang Tanggal</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fas fa-calendar"></i>
                                                </div>
                                            </div>
                                            <input type="text" class="form-control daterange" name="date_range">
                                            <input type="hidden" name="start_date" id="outlet_start_date">
                                            <input type="hidden" name="end_date" id="outlet_end_date">
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

                    <!-- Kartu Laporan Kinerja Produk -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Laporan Kinerja Produk</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.product-performance') }}" method="GET" target="_blank">
                                    <div class="form-group">
                                        <label>Rentang Tanggal</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fas fa-calendar"></i>
                                                </div>
                                            </div>
                                            <input type="text" class="form-control daterange" name="date_range">
                                            <input type="hidden" name="start_date" id="product_start_date">
                                            <input type="hidden" name="end_date" id="product_end_date">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Outlet</label>
                                        <select class="form-control select2" name="outlet_id">
                                            <option value="">Semua Outlet</option>
                                            @foreach($outlets as $outlet)
                                                <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                            @endforeach
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

                    <!-- Kartu Laporan Analisis Pelanggan -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Laporan Analisis Pelanggan</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.customer-insights') }}" method="GET" target="_blank">
                                    <div class="form-group">
                                        <label>Rentang Tanggal</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">
                                                    <i class="fas fa-calendar"></i>
                                                </div>
                                            </div>
                                            <input type="text" class="form-control daterange" name="date_range">
                                            <input type="hidden" name="start_date" id="customer_start_date">
                                            <input type="hidden" name="end_date" id="customer_end_date">
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

                    <!-- Kartu Laporan Inventaris -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Laporan Inventaris & Bahan Baku</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.inventory') }}" method="GET" target="_blank">
                                    <p>Laporan ini menampilkan tingkat inventaris saat ini dan pesanan bahan baku terbaru. Tidak perlu rentang tanggal.</p>
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
            // Inisialisasi select2
            $('.select2').select2();

            // Inisialisasi daterangepicker untuk semua form
            $('.daterange').daterangepicker({
                locale: {
                    format: 'YYYY-MM-DD'
                },
                drops: 'down',
                opens: 'right'
            });

            // Perbarui field start_date dan end_date tersembunyi saat rentang tanggal berubah
            $('.daterange').on('apply.daterangepicker', function(ev, picker) {
                var formId = $(this).closest('form').attr('action');

                if (formId.includes('sales-summary')) {
                    $('#sales_start_date').val(picker.startDate.format('YYYY-MM-DD'));
                    $('#sales_end_date').val(picker.endDate.format('YYYY-MM-DD'));
                } else if (formId.includes('outlet-performance')) {
                    $('#outlet_start_date').val(picker.startDate.format('YYYY-MM-DD'));
                    $('#outlet_end_date').val(picker.endDate.format('YYYY-MM-DD'));
                } else if (formId.includes('product-performance')) {
                    $('#product_start_date').val(picker.startDate.format('YYYY-MM-DD'));
                    $('#product_end_date').val(picker.endDate.format('YYYY-MM-DD'));
                } else if (formId.includes('customer-insights')) {
                    $('#customer_start_date').val(picker.startDate.format('YYYY-MM-DD'));
                    $('#customer_end_date').val(picker.endDate.format('YYYY-MM-DD'));
                }
            });

            // Set rentang tanggal default (30 hari terakhir)
            var start = moment().subtract(29, 'days');
            var end = moment();

            $('.daterange').each(function() {
                $(this).data('daterangepicker').setStartDate(start);
                $(this).data('daterangepicker').setEndDate(end);
            });

            // Set nilai awal field tersembunyi
            $('#sales_start_date').val(start.format('YYYY-MM-DD'));
            $('#sales_end_date').val(end.format('YYYY-MM-DD'));
            $('#outlet_start_date').val(start.format('YYYY-MM-DD'));
            $('#outlet_end_date').val(end.format('YYYY-MM-DD'));
            $('#product_start_date').val(start.format('YYYY-MM-DD'));
            $('#product_end_date').val(end.format('YYYY-MM-DD'));
            $('#customer_start_date').val(start.format('YYYY-MM-DD'));
            $('#customer_end_date').val(end.format('YYYY-MM-DD'));
        });
    </script>
@endpush
