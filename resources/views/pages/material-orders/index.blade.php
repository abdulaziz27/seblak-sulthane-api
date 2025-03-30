@extends('layouts.app')

@section('title', 'Pesanan Material')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Pesanan Material</h1>
                <div class="section-header-button">
                    <a href="{{ route('material-orders.create') }}" class="btn btn-primary">Buat Pesanan</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventori</a></div>
                    <div class="breadcrumb-item">Pesanan Material</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Filter Pesanan</h4>
                            </div>
                            <div class="card-body">
                                <form method="GET" action="{{ route('material-orders.index') }}" id="filter-form">
                                    <div class="row">
                                        <div class="form-group col-md-3">
                                            <label class="d-block">Rentang Tanggal</label>
                                            <a href="javascript:;" class="btn btn-primary daterange-btn icon-left btn-icon"
                                                id="daterange-btn">
                                                <i class="fas fa-calendar"></i>
                                                <span>Pilih Rentang Tanggal</span>
                                            </a>
                                            <input type="hidden" name="date_start" id="date_start"
                                                value="{{ request('date_start') }}">
                                            <input type="hidden" name="date_end" id="date_end"
                                                value="{{ request('date_end') }}">
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label>Status</label>
                                            <select class="form-control selectric" name="status">
                                                <option value="">Semua Status</option>
                                                <option value="pending"
                                                    {{ request('status') == 'pending' ? 'selected' : '' }}>
                                                    Pending
                                                </option>
                                                <option value="approved"
                                                    {{ request('status') == 'approved' ? 'selected' : '' }}>
                                                    Disetujui
                                                </option>
                                                <option value="delivered"
                                                    {{ request('status') == 'delivered' ? 'selected' : '' }}>
                                                    Dikirim
                                                </option>
                                            </select>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label>Metode Pembayaran</label>
                                            <select class="form-control selectric" name="payment_method">
                                                <option value="">Semua Metode Pembayaran</option>
                                                <option value="cash"
                                                    {{ request('payment_method') == 'cash' ? 'selected' : '' }}>Tunai
                                                </option>
                                                <option value="bank_transfer"
                                                    {{ request('payment_method') == 'bank_transfer' ? 'selected' : '' }}>
                                                    Transfer Bank</option>
                                                <option value="e-wallet"
                                                    {{ request('payment_method') == 'e-wallet' ? 'selected' : '' }}>
                                                    E-Wallet</option>
                                            </select>
                                        </div>

                                        @if (Auth::user()->role === 'owner')
                                            <div class="form-group col-md-3">
                                                <label>Outlet</label>
                                                <select class="form-control selectric" name="franchise_id">
                                                    <option value="">Semua Outlet</option>
                                                    @foreach ($outlets as $outlet)
                                                        <option value="{{ $outlet->id }}"
                                                            {{ request('franchise_id') == $outlet->id ? 'selected' : '' }}>
                                                            {{ $outlet->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif

                                        <div class="form-group col-md-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary mr-2">Filter</button>
                                            <a href="{{ route('material-orders.index') }}"
                                                class="btn btn-secondary">Reset</a>
                                        </div>
                                    </div>

                                    @if (request()->has('page'))
                                        <input type="hidden" name="page" value="{{ request('page') }}">
                                    @endif
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Daftar Pesanan Material</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID Pesanan</th>
                                                <th>Outlet</th>
                                                <th>Dibuat Oleh</th>
                                                <th>Tanggal</th>
                                                <th>Metode Pembayaran</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($materialOrders as $order)
                                                <tr>
                                                    <td>#{{ $order->id }}</td>
                                                    <td>{{ $order->franchise->name }}</td>
                                                    <td>{{ $order->user->name }}</td>
                                                    <td>{{ $order->created_at->format('d M Y H:i') }}</td>
                                                    <td>{{ $order->formatted_payment_method }}</td>
                                                    <td>{{ $order->formatted_total }}</td>
                                                    <td>{!! $order->status_badge !!}</td>
                                                    <td>
                                                        <a href="{{ route('material-orders.show', $order->id) }}"
                                                            class="btn btn-info btn-sm">
                                                            <i class="fas fa-eye"></i>
                                                        </a>

                                                        @if ($order->status === 'pending')
                                                            @if (Auth::user()->role === 'owner' || Auth::user()->isWarehouseStaff() || $order->user_id === Auth::id())
                                                                <a href="{{ route('material-orders.edit', $order->id) }}"
                                                                    class="btn btn-sm btn-primary">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <form
                                                                    action="{{ route('material-orders.cancel', $order->id) }}"
                                                                    method="POST" class="d-inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                                        onclick="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </form>
                                                            @endif

                                                            {{-- @if ($isWarehouse)
                                                                <form
                                                                    action="{{ route('material-orders.update-status', $order->id) }}"
                                                                    method="POST" class="d-inline">
                                                                    @csrf
                                                                    <input type="hidden" name="status" value="approved">
                                                                    <button type="submit" class="btn btn-sm btn-success"
                                                                        onclick="return confirm('Setujui pesanan ini?')">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                </form>
                                                            @endif --}}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer">
                                {{ $materialOrders->links() }}
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

            // Auto-submit when selecting a date
            $('.datepicker').on('changeDate', function() {
                $('#filter-form').submit();
            });

            $('.daterange-cus').on('apply.daterangepicker', function(ev, picker) {
                setTimeout(function() {
                    $('#filter-form').submit();
                }, 300);
            });

            // Confirm delete functionality
            $('.confirm-delete').click(function(e) {
                var form = $(this).closest('form');
                e.preventDefault();

                swal({
                    title: 'Apakah Anda yakin?',
                    text: 'Setelah dibatalkan, Anda tidak dapat memulihkan pesanan ini!',
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
