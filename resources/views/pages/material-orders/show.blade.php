@extends('layouts.app')

@section('title', 'Order Details')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Material Order Details</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventory</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('material-orders.index') }}">Material Orders</a></div>
                    <div class="breadcrumb-item">Details</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Order #{{ $materialOrder->id }}</h4>
                                <div class="card-header-action">
                                    @if ($materialOrder->status === 'pending')
                                        <a href="{{ route('material-orders.edit', $materialOrder->id) }}" class="btn btn-primary mr-2">
                                            <i class="fas fa-edit"></i> Edit Order
                                        </a>
                                        <form action="{{ route('material-orders.cancel', $materialOrder->id) }}"
                                            method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger confirm-delete">
                                                <i class="fas fa-times"></i> Cancel Order
                                            </button>
                                        </form>
                                    @endif

                                    @if (Auth::user()->role === 'owner' && $materialOrder->status === 'pending')
                                        <form action="{{ route('material-orders.update-status', $materialOrder->id) }}"
                                            method="POST" class="d-inline ml-2">
                                            @csrf
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="btn btn-info">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                    @endif

                                    @if (Auth::user()->role === 'owner' && $materialOrder->status === 'approved')
                                        <form action="{{ route('material-orders.update-status', $materialOrder->id) }}"
                                            method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="status" value="delivered">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-truck"></i> Mark as Delivered
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table">
                                            <tr>
                                                <th>Outlet</th>
                                                <td>{{ $materialOrder->franchise->name }}</td>
                                            </tr>
                                            <tr>
                                                <th>Created By</th>
                                                <td>{{ $materialOrder->user->name }}</td>
                                            </tr>
                                            <tr>
                                                <th>Order Date</th>
                                                <td>{{ $materialOrder->created_at->format('d M Y H:i') }}</td>
                                            </tr>
                                            <tr>
                                                <th>Notes</th>
                                                <td>{{ $materialOrder->notes ?? 'No notes' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table">
                                            <tr>
                                                <th>Status</th>
                                                <td>{!! $materialOrder->status_badge !!}</td>
                                            </tr>
                                            <tr>
                                                <th>Payment Method</th>
                                                <td>{{ $materialOrder->formatted_payment_method }}</td>
                                            </tr>
                                            @if ($materialOrder->approved_at)
                                                <tr>
                                                    <th>Approved At</th>
                                                    <td>{{ $materialOrder->approved_at->format('d M Y H:i') }}</td>
                                                </tr>
                                            @endif
                                            @if ($materialOrder->delivered_at)
                                                <tr>
                                                    <th>Delivered At</th>
                                                    <td>{{ $materialOrder->delivered_at->format('d M Y H:i') }}</td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <th>Total Amount</th>
                                                <td>{{ $materialOrder->formatted_total }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <h5 class="mt-4">Order Items</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Material</th>
                                                <th>Unit</th>
                                                <th>Price per Unit</th>
                                                <th>Quantity</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($materialOrder->items as $index => $item)
                                                <tr>
                                                    <td>{{ $index + 1 }}</td>
                                                    <td>{{ $item->rawMaterial->name }}</td>
                                                    <td>{{ $item->rawMaterial->unit }}</td>
                                                    <td>Rp {{ number_format($item->price_per_unit, 0, ',', '.') }}</td>
                                                    <td>{{ $item->quantity }}</td>
                                                    <td>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5" class="text-right"><strong>Total:</strong></td>
                                                <td><strong>{{ $materialOrder->formatted_total }}</strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer text-right">
                                <a href="{{ route('material-orders.index') }}" class="btn btn-primary">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
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
    <script src="{{ asset('library/sweetalert/dist/sweetalert.min.js') }}"></script>

    <!-- Page Specific JS File -->
    <script>
        // Confirm delete
        $('.confirm-delete').click(function(e) {
            var form = $(this).closest('form');
            e.preventDefault();

            swal({
                    title: 'Are you sure?',
                    text: 'Once cancelled, you will not be able to recover this order!',
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true,
                })
                .then((willDelete) => {
                    if (willDelete) {
                        form.submit();
                    }
                });
        });
    </script>
@endpush
