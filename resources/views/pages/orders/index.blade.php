@extends('layouts.app')

@section('title', 'Orders')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-daterangepicker/daterangepicker.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Daftar Orders</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Orders</a></div>
                    <div class="breadcrumb-item">All Orders</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Filter Orders</h4>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="{{ route('orders.index') }}" id="filter-form">
                                    <div class="row">
                                        <div class="form-group col-md-3">
                                            <label class="d-block">Date Range</label>
                                            <a href="javascript:;" class="btn btn-primary daterange-btn icon-left btn-icon"
                                                id="daterange-btn">
                                                <i class="fas fa-calendar"></i>
                                                <span>Choose Date Range</span>
                                            </a>
                                            <input type="hidden" name="date_start" id="date_start"
                                                value="{{ request('date_start') }}">
                                            <input type="hidden" name="date_end" id="date_end"
                                                value="{{ request('date_end') }}">
                                        </div>

                                        <!-- Filter outlet hanya untuk owner -->
                                        @if (Auth::user()->role === 'owner')
                                            <div class="form-group col-md-3">
                                                <label>Outlet</label>
                                                <select class="form-control selectric" name="outlet_id">
                                                    <option value="">All Outlets</option>
                                                    @foreach ($outlets as $outlet)
                                                        <option value="{{ $outlet->id }}"
                                                            {{ request('outlet_id') == $outlet->id ? 'selected' : '' }}>
                                                            {{ $outlet->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif

                                        <div class="form-group col-md-3">
                                            <label>Payment Method</label>
                                            <select class="form-control selectric" name="payment_method">
                                                <option value="">All Methods</option>
                                                <option value="cash" {{ request('payment_method') == 'cash' ? 'selected' : '' }}>CASH</option>
                                                <option value="qris" {{ request('payment_method') == 'qris' ? 'selected' : '' }}>QRIS</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>&nbsp;</label>
                                            <button type="submit" class="btn btn-primary d-block w-100">Filter</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Date/Time</th>
                                            <th>Outlet</th>
                                            <th>Kasir</th>
                                            <th>Payment Method</th>
                                            <th>Total Items</th>
                                            <th>Subtotal</th>
                                            <th>Discount</th>
                                            <th>Tax</th>
                                            <th>Total</th>
                                            <th>Action</th>
                                        </tr>
                                        @foreach ($orders as $order)
                                            <tr>
                                                <td>#{{ $order->id }}</td>
                                                <td>{{ Carbon\Carbon::parse($order->transaction_time)->format('d M Y H:i') }}
                                                </td>
                                                <td>{{ $order->outlet->name }}</td>
                                                <td>{{ $order->nama_kasir }}</td>
                                                <td>{{ strtoupper($order->payment_method) }}</td>
                                                <td>{{ $order->total_item }}</td>
                                                <td>Rp {{ number_format($order->sub_total, 0, ',', '.') }}</td>
                                                <td>Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</td>
                                                <td>Rp {{ number_format($order->tax, 0, ',', '.') }}</td>
                                                <td>Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                                                <td>
                                                    <a href="{{ route('orders.show', $order->id) }}"
                                                        class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $orders->withQueryString()->links() }}
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
    <!-- JS Libraies -->
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>
    <script src="{{ asset('library/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('library/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}"></script>
    <script src="{{ asset('library/sweetalert/dist/sweetalert.min.js') }}"></script>

    <!-- Page Specific JS File -->
    <script src="{{ asset('js/page/forms-advanced-forms.js') }}"></script>

    <script>
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
                $('#date_start').val(start.format('YYYY-MM-DD'));
                $('#date_end').val(end.format('YYYY-MM-DD'));
            });

            // Set current values if they exist
            @if (request('date_start') && request('date_end'))
                $('#daterange-btn span').html(
                    "{{ \Carbon\Carbon::parse(request('date_start'))->format('MMMM D, YYYY') }} - {{ \Carbon\Carbon::parse(request('date_end'))->format('MMMM D, YYYY') }}"
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
    </script>
@endpush
