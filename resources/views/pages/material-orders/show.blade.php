@extends('layouts.app')

@section('title', 'Detail Pesanan')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Detail Pesanan Bahan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventaris</a></div>
                    <div class="breadcrumb-item"><a href="{{ route('material-orders.index') }}">Pesanan Bahan</a></div>
                    <div class="breadcrumb-item">Detail</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Pesanan #{{ $materialOrder->id }}</h4>
                                <div class="card-header-action">
                                    @if ($materialOrder->status === 'pending')
                                        <a href="{{ route('material-orders.edit', $materialOrder->id) }}"
                                            class="btn btn-primary mr-2">
                                            <i class="fas fa-edit"></i> Edit Pesanan
                                        </a>
                                        <form action="{{ route('material-orders.cancel', $materialOrder->id) }}"
                                            method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger confirm-delete">
                                                <i class="fas fa-times"></i> Batalkan Pesanan
                                            </button>
                                        </form>
                                    @endif

                                    @if ($materialOrder->status === 'pending' && isset($isWarehouse) && $isWarehouse)
                                        <form action="{{ route('material-orders.update-status', $materialOrder->id) }}"
                                            method="POST" class="d-inline ml-2">
                                            @csrf
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="btn btn-info">
                                                <i class="fas fa-check"></i> Setujui
                                            </button>
                                        </form>
                                    @endif

                                    @if ($materialOrder->status === 'approved' && isset($isWarehouse) && $isWarehouse)
                                        <form action="{{ route('material-orders.update-status', $materialOrder->id) }}"
                                            method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="status" value="delivered">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-truck"></i> Tandai Telah Diterima
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
                                                <th>Dibuat Oleh</th>
                                                <td>{{ $materialOrder->user->name }}</td>
                                            </tr>
                                            <tr>
                                                <th>Tanggal Pesanan</th>
                                                <td>{{ $materialOrder->created_at->format('d M Y H:i') }}</td>
                                            </tr>
                                            <tr>
                                                <th>Catatan</th>
                                                <td>{{ $materialOrder->notes ?? 'Tidak ada catatan' }}</td>
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
                                                <th>Metode Pembayaran</th>
                                                <td>{{ $materialOrder->formatted_payment_method }}</td>
                                            </tr>
                                            @if ($materialOrder->approved_at)
                                                <tr>
                                                    <th>Disetujui Pada</th>
                                                    <td>{{ $materialOrder->approved_at->format('d M Y H:i') }}</td>
                                                </tr>
                                            @endif
                                            @if ($materialOrder->delivered_at)
                                                <tr>
                                                    <th>Diterima Pada</th>
                                                    <td>{{ $materialOrder->delivered_at->format('d M Y H:i') }}</td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <th>Total Jumlah</th>
                                                <td>{{ $materialOrder->formatted_total }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <h5 class="mt-4">Item Pesanan</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Bahan</th>
                                                <th>Satuan</th>
                                                <th>Harga per Satuan</th>
                                                <th>Jumlah</th>
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
                                <a href="{{ route('material-orders.download', $materialOrder->id) }}"
                                    class="btn btn-success">
                                    <i class="fas fa-file-download"></i> Download Invoice
                                </a>
                                <a href="{{ route('material-orders.index') }}" class="btn btn-primary">
                                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
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
                    title: 'Apakah Anda yakin?',
                    text: 'Setelah dibatalkan, Anda tidak dapat memulihkan pesanan ini!',
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
