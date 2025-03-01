@extends('layouts.app')

@section('title', 'Reports Dashboard')

@push('style')
    <link rel="stylesheet" href="{{ asset('library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('library/select2/dist/css/select2.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Reports Dashboard</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item">Reports</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Download Reports</h2>
                <p class="section-lead">
                    Select the report type and date range, then click on the download button to get your report in PDF or Excel format.
                </p>

                <div class="row">
                    <!-- Sales Summary Report Card -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Sales Summary Report</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.sales-summary') }}" method="GET" target="_blank">
                                    <div class="form-group">
                                        <label>Date Range</label>
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
                                            <option value="">All Outlets</option>
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
                                        <button type="submit" class="btn btn-primary">Download Report</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Outlet Performance Report Card -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Outlet Performance Report</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.outlet-performance') }}" method="GET" target="_blank">
                                    <div class="form-group">
                                        <label>Date Range</label>
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
                                        <button type="submit" class="btn btn-primary">Download Report</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Product Performance Report Card -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Product Performance Report</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.product-performance') }}" method="GET" target="_blank">
                                    <div class="form-group">
                                        <label>Date Range</label>
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
                                            <option value="">All Outlets</option>
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
                                        <button type="submit" class="btn btn-primary">Download Report</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Insights Report Card -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Customer Insights Report</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.customer-insights') }}" method="GET" target="_blank">
                                    <div class="form-group">
                                        <label>Date Range</label>
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
                                        <button type="submit" class="btn btn-primary">Download Report</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Report Card -->
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Inventory & Raw Materials Report</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('reports.inventory') }}" method="GET" target="_blank">
                                    <p>This report shows current inventory levels and recent material orders. No date range required.</p>
                                    <div class="form-group">
                                        <label>Format</label>
                                        <select class="form-control" name="format">
                                            <option value="pdf">PDF</option>
                                            <option value="excel">Excel</option>
                                        </select>
                                    </div>
                                    <div class="text-right">
                                        <button type="submit" class="btn btn-primary">Download Report</button>
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
            // Initialize select2
            $('.select2').select2();

            // Initialize daterangepicker for all forms
            $('.daterange').daterangepicker({
                locale: {
                    format: 'YYYY-MM-DD'
                },
                drops: 'down',
                opens: 'right'
            });

            // Update hidden start_date and end_date fields when date range changes
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

            // Set default date range (last 30 days)
            var start = moment().subtract(29, 'days');
            var end = moment();

            $('.daterange').each(function() {
                $(this).data('daterangepicker').setStartDate(start);
                $(this).data('daterangepicker').setEndDate(end);
            });

            // Set initial hidden field values
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
