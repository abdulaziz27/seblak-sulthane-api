@extends('layouts.app')

@section('title', 'Member Detail')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/chocolat/dist/css/chocolat.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Member Detail</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Members</a></div>
                    <div class="breadcrumb-item">Detail</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row mt-sm-4">
                    <!-- Member Information Card -->
                    <div class="col-12 col-md-12 col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h4>Member Information</h4>
                            </div>
                            <div class="card-body">
                                <div class="py-4">
                                    <p class="clearfix">
                                        <span class="float-left">Name</span>
                                        <span class="float-right text-muted">{{ $member->name }}</span>
                                    </p>
                                    <p class="clearfix">
                                        <span class="float-left">Phone Number</span>
                                        <span class="float-right text-muted">{{ $member->phone }}</span>
                                    </p>
                                    <p class="clearfix">
                                        <span class="float-left">Member Since</span>
                                        <span class="float-right text-muted">{{ $member->created_at->format('d M Y') }}</span>
                                    </p>
                                    <p class="clearfix">
                                        <span class="float-left">Total Orders</span>
                                        <span class="float-right text-muted">{{ $orders->total() }}</span>
                                    </p>
                                    <p class="clearfix">
                                        <span class="float-left">Total Spending</span>
                                        <span class="float-right text-muted">Rp {{ number_format($totalSpending, 0, ',', '.') }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="card-footer text-center">
                                <a href="{{ route('members.edit', $member->id) }}" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Edit Member
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Order History Card -->
                    <div class="col-12 col-md-12 col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4>Order History</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Date</th>
                                            <th>Total Items</th>
                                            <th>Total Amount</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                        @foreach($orders as $order)
                                            <tr>
                                                <td>#{{ $order->id }}</td>
                                                <td>{{ $order->created_at->format('d M Y H:i') }}</td>
                                                <td>{{ $order->total_item }}</td>
                                                <td>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                                                <td>
                                                    <div class="badge badge-{{ $order->status === 'completed' ? 'success' : ($order->status === 'pending' ? 'warning' : 'danger') }}">
                                                        {{ ucfirst($order->status) }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#orderModal{{ $order->id }}">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>

                                            <!-- Order Detail Modal -->
                                            <div class="modal fade" id="orderModal{{ $order->id }}" tabindex="-1" role="dialog" aria-labelledby="orderModalLabel" aria-hidden="true">
                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="orderModalLabel">Order #{{ $order->id }} Detail</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p><strong>Order Date:</strong> {{ $order->created_at->format('d M Y H:i') }}</p>
                                                                    <p><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
                                                                    <p><strong>Payment Method:</strong> {{ ucfirst($order->payment_method) }}</p>
                                                                </div>
                                                                <div class="col-md-6 text-right">
                                                                    <p><strong>Subtotal:</strong> Rp {{ number_format($order->subtotal, 0, ',', '.') }}</p>
                                                                    <p><strong>Tax:</strong> Rp {{ number_format($order->tax_amount, 0, ',', '.') }}</p>
                                                                    <p><strong>Discount:</strong> Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</p>
                                                                    <p><strong>Total:</strong> Rp {{ number_format($order->total_amount, 0, ',', '.') }}</p>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h6>Order Items</h6>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Item</th>
                                                                            <th>Price</th>
                                                                            <th>Quantity</th>
                                                                            <th>Subtotal</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($order->order_items as $item)
                                                                            <tr>
                                                                                <td>{{ $item->product->name }}</td>
                                                                                <td>Rp {{ number_format($item->price_per_unit, 0, ',', '.') }}</td>
                                                                                <td>{{ $item->quantity }}</td>
                                                                                <td>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $orders->links() }}
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
    <script src="{{ asset('library/chocolat/dist/js/jquery.chocolat.min.js') }}"></script>
@endpush
