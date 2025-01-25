@extends('layouts.app')

@section('title', 'Orders')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
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
                                <form method="GET" action="{{ route('orders.index') }}">
                                    <div class="row">
                                        <div class="form-group col-3">
                                            <label>Date</label>
                                            <input type="date" class="form-control" name="date"
                                                value="{{ request('date') }}">
                                        </div>
                                        <!-- Filter outlet hanya untuk owner -->
                                        @if (Auth::user()->role === 'owner')
                                            <div class="form-group col-3">
                                                <label>Outlet</label>
                                                <select class="form-control" name="outlet_id">
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
                                        <div class="form-group col-3">
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

    <!-- Page Specific JS File -->
    <script src="{{ asset('js/page/features-posts.js') }}"></script>
@endpush
