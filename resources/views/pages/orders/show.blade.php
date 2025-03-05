@extends('layouts.app')

@section('title', 'Order Detail')

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Order Detail #{{ $order->id }}</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('orders.index') }}">Orders</a></div>
                    <div class="breadcrumb-item">Detail</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table">
                                            <tr>
                                                <th width="200">Order ID</th>
                                                <td>#{{ $order->id }}</td>
                                            </tr>
                                            <tr>
                                                <th>Transaction Time</th>
                                                <td>{{ Carbon\Carbon::parse($order->transaction_time)->format('d M Y H:i') }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Outlet</th>
                                                <td>{{ $order->outlet->name }}</td>
                                            </tr>
                                            <tr>
                                                <th>Kasir</th>
                                                <td>{{ $order->nama_kasir }}</td>
                                            </tr>
                                            <tr>
                                                <th>Payment Method</th>
                                                <td>{{ strtoupper($order->payment_method) }}</td>
                                            </tr>
                                            <tr>
                                                <th>Order Type</th>
                                                <td>{{ ucfirst(str_replace('_', ' ', $order->order_type)) }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table">
                                            <tr>
                                                <th width="200">Subtotal</th>
                                                <td>Rp {{ number_format($order->sub_total, 0, ',', '.') }}</td>
                                            </tr>
                                            <tr>
                                                <th>Discount</th>
                                                <td>Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</td>
                                            </tr>
                                            <tr>
                                                <th>Tax</th>
                                                <td>Rp {{ number_format($order->tax, 0, ',', '.') }}</td>
                                            </tr>
                                            <tr>
                                                <th>Service Charge</th>
                                                <td>Rp {{ number_format($order->service_charge, 0, ',', '.') }}</td>
                                            </tr>
                                            <tr>
                                                <th>Total</th>
                                                <td>Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h5>Order Items</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Product</th>
                                                        <th>Price</th>
                                                        <th>Quantity</th>
                                                        <th>Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($order->orderItems as $index => $item)
                                                        <tr>
                                                            <td>{{ $index + 1 }}</td>
                                                            <td>{{ $item->product->name }}</td>
                                                            <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                                                            <td>{{ $item->quantity }}</td>
                                                            <td>Rp
                                                                {{ number_format($item->price * $item->quantity, 0, ',', '.') }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
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
