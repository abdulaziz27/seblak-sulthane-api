@extends('layouts.app')

@section('title', 'Member')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Member</h1>
                <div class="section-header-button">
                    <a href="{{ route('members.create') }}" class="btn btn-primary">Tambah Baru</a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Member</a></div>
                    <div class="breadcrumb-item">Semua Member</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="float-right">
                                    <form method="GET" action="{{ route('members.index') }}">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Cari nama/telepon"
                                                name="search">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <div class="clearfix mb-3"></div>

                                <div class="table-responsive">
                                    <table class="table-striped table">
                                        <tr>
                                            <th>Nama</th>
                                            <th>Telepon</th>
                                            <th>Total Belanja</th>
                                            <th>Dibuat Pada</th>
                                            <th>Aksi</th>
                                        </tr>
                                        @foreach ($members as $member)
                                            <tr>
                                                <td>{{ $member->name }}</td>
                                                <td>{{ $member->phone }}</td>
                                                <td>Rp {{ number_format($member->total_spending ?? 0, 0, ',', '.') }}</td>
                                                <td>{{ $member->created_at }}</td>
                                                <td>
                                                    <div class="d-flex justify-content-center">
                                                        <a href="{{ route('members.edit', $member->id) }}"
                                                            class="btn btn-sm btn-primary btn-icon mr-1">
                                                            <i class="fas fa-edit"></i>
                                                            Edit
                                                        </a>
                                                        <form action="{{ route('members.destroy', $member->id) }}"
                                                            method="POST" class="ml-2">
                                                            <input type="hidden" name="_method" value="DELETE" />
                                                            <input type="hidden" name="_token"
                                                                value="{{ csrf_token() }}" />
                                                            <button class="btn btn-sm btn-danger btn-icon confirm-delete">
                                                                <i class="fas fa-times"></i> Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $members->withQueryString()->links() }}
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
