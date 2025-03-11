@extends('layouts.app')

@section('title', 'Diskon')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Diskon</h1>
                {{-- <div class="section-header-button">
                    <a href="{{ route('discounts.create') }}" class="btn btn-primary">Tambah Baru</a>
                </div> --}}
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <tr>
                                            <th>Nama</th>
                                            <th>Tipe</th>
                                            <th>Nilai</th>
                                            <th>Kategori</th>
                                            <th>Tanggal Kedaluwarsa</th>
                                            {{-- <th>Status</th>
                                            <th>Aksi</th> --}}
                                        </tr>
                                        @foreach ($discounts as $discount)
                                            <tr>
                                                <td>{{ $discount->name }}</td>
                                                <td>{{ ucfirst($discount->type) }}</td>
                                                <td>{{ $discount->type === 'percentage' ? $discount->value . '%' : 'Rp ' . number_format($discount->value) }}</td>
                                                <td>{{ ucfirst($discount->category) }}</td>
                                                <td>{{ $discount->expired_date ? date('d M Y', strtotime($discount->expired_date)) : 'Tidak Ada Kedaluwarsa' }}</td>
                                                {{-- <td>
                                                    <div class="badge badge-{{ $discount->status === 'active' ? 'success' : 'danger' }}">
                                                        {{ ucfirst($discount->status) }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex justify-content-center">
                                                        <a href='{{ route('discounts.edit', $discount->id) }}'
                                                            class="btn btn-sm btn-info btn-icon">
                                                            <i class="fas fa-edit"></i>
                                                            Edit
                                                        </a>

                                                        <form action="{{ route('discounts.destroy', $discount->id) }}"
                                                            method="POST" class="ml-2">
                                                            <input type="hidden" name="_method" value="DELETE" />
                                                            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                                                            <button class="btn btn-sm btn-danger btn-icon confirm-delete">
                                                                <i class="fas fa-times"></i> Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td> --}}
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $discounts->withQueryString()->links() }}
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
    <script src="{{ asset('library/sweetalert/dist/sweetalert.min.js') }}"></script>

    <!-- Page Specific JS File -->
    <script src="{{ asset('js/page/features-posts.js') }}"></script>
@endpush
