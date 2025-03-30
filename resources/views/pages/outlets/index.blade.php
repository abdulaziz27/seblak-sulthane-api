@extends('layouts.app')

@section('title', 'Outlet')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Daftar Outlet</h1>
                @if (Auth::user()->role === 'owner')
                    <div class="section-header-button d-none d-md-flex">
                        <a href="{{ route('outlets.create') }}" class="btn btn-primary">Tambah Baru</a>
                        <button type="button" class="btn btn-success ml-2" data-toggle="modal" data-target="#importModal">
                            Import Excel
                        </button>
                        <a href="{{ route('outlets.export') }}" class="btn btn-info ml-2">
                            Export Excel
                        </a>
                        <button type="button" class="btn btn-warning ml-2" data-toggle="modal"
                            data-target="#bulkUpdateModal">
                            Update Massal
                        </button>
                        <button type="button" class="btn btn-danger ml-2" data-toggle="modal"
                            data-target="#deleteAllModal">
                            Hapus Semua
                        </button>
                    </div>
                @endif
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Outlet</a></div>
                    <div class="breadcrumb-item">Semua Outlet</div>
                </div>
            </div>
            <div class="section-body">
                @if (Auth::user()->role === 'owner')
                    <!-- Responsive action buttons for small screens -->
                    <div class="d-flex d-md-none mb-4 flex-wrap justify-content-center">
                        <a href="{{ route('outlets.create') }}" class="btn btn-primary m-1">
                            <i class="fas fa-plus mr-1"></i> Tambah
                        </a>
                        <button type="button" class="btn btn-success m-1" data-toggle="modal" data-target="#importModal">
                            <i class="fas fa-file-import mr-1"></i> Import
                        </button>
                        <a href="{{ route('outlets.export') }}" class="btn btn-info m-1">
                            <i class="fas fa-file-export mr-1"></i> Export
                        </a>
                        <button type="button" class="btn btn-warning m-1" data-toggle="modal"
                            data-target="#bulkUpdateModal">
                            <i class="fas fa-sync-alt mr-1"></i> Update
                        </button>
                        <button type="button" class="btn btn-danger m-1" data-toggle="modal" data-target="#deleteAllModal">
                            <i class="fas fa-trash mr-1"></i> Hapus
                        </button>
                    </div>
                @endif

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
                                    <form method="GET" action="{{ route('outlets.index') }}">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Cari" name="name">
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
                                            <th>Alamat</th>
                                            <th>Telepon</th>
                                            <th>Pimpinan</th>
                                            <th>Gudang</th>

                                            {{-- <th>Dibuat Pada</th> --}}
                                            @if (Auth::user()->role === 'owner')
                                                <th>Aksi</th>
                                            @endif
                                        </tr>
                                        @foreach ($outlets as $outlet)
                                            <tr>
                                                <td>{{ $outlet->name }}</td>
                                                <td>
                                                    {{ $outlet->address1 }}
                                                    @if ($outlet->address2)
                                                        <br><small>{{ $outlet->address2 }}</small>
                                                    @endif
                                                </td>
                                                <td>{{ $outlet->phone }}</td>
                                                <td>{{ $outlet->leader }}</td>
                                                <td>
                                                    @if ($outlet->is_warehouse)
                                                        <div class="badge badge-success">Ya</div>
                                                    @else
                                                        <div class="badge badge-secondary">Tidak</div>
                                                    @endif
                                                </td>
                                                {{-- <td>{{ $outlet->created_at->format('d M Y') }}</td> --}}
                                                @if (Auth::user()->role === 'owner')
                                                    <td>
                                                        <div class="d-flex justify-content-center">
                                                            <a href='{{ route('outlets.edit', $outlet->id) }}'
                                                                class="btn btn-sm btn-info btn-icon">
                                                                <i class="fas fa-edit"></i>
                                                                Edit
                                                            </a>

                                                            <form action="{{ route('outlets.destroy', $outlet->id) }}"
                                                                method="POST" class="ml-2">
                                                                <input type="hidden" name="_method" value="DELETE" />
                                                                <input type="hidden" name="_token"
                                                                    value="{{ csrf_token() }}" />
                                                                <button
                                                                    class="btn btn-sm btn-danger btn-icon confirm-delete">
                                                                    <i class="fas fa-times"></i> Hapus
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $outlets->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Outlet</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('outlets.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>File Excel</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Petunjuk:</h6>
                            <ol>
                                <li>Unduh template <a href="{{ route('outlets.template') }}"
                                        class="font-weight-bold text-primary">disini</a></li>
                                <li>Isi data sesuai dengan template</li>
                                <li>Simpan dan unggah filenya</li>
                            </ol>
                            <p>Urutan kolom: NAMA OUTLET, ALAMAT 1, ALAMAT 2, NO. TELP, PIMPINAN, KET</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Update Modal -->
    <div class="modal fade" id="bulkUpdateModal" tabindex="-1" role="dialog" aria-labelledby="bulkUpdateModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkUpdateModalLabel">Update Massal Outlet</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('outlets.bulkUpdate') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>File Excel</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Petunjuk:</h6>
                            <ol>
                                <li>Unduh template update <a href="{{ route('outlets.exportForUpdate') }}"
                                        class="font-weight-bold text-primary">disini</a></li>
                                <li>Perbarui data sesuai kebutuhan</li>
                                <li>Simpan dan unggah filenya</li>
                            </ol>
                            <p>Urutan kolom: ID, NAMA OUTLET, ALAMAT 1, ALAMAT 2, NO. TELP, PIMPINAN, KET</p>
                            <p class="text-warning"><strong>Catatan:</strong> Jangan mengubah kolom ID karena akan
                                digunakan sebagai referensi untuk pembaruan.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">Update Outlet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete All Modal -->
    <div class="modal fade" id="deleteAllModal" tabindex="-1" role="dialog" aria-labelledby="deleteAllModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteAllModalLabel">Hapus Semua Outlet</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus semua outlet? Tindakan ini tidak dapat dibatalkan.</p>
                    <p class="text-danger"><strong>Peringatan:</strong> Outlet yang dihapus akan disembunyikan dari
                        tampilan.</p>
                </div>
                <div class="modal-footer">
                    <form action="{{ route('outlets.deleteAll') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus Semua Outlet</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- JS Libraies -->
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>
    <script src="{{ asset('library/sweetalert/dist/sweetalert.min.js') }}"></script>

    <!-- Page Specific JS File -->
    <script src="{{ asset('js/page/features-posts.js') }}"></script>

    <script>
        // Confirm delete for single item deletion
        $('.confirm-delete').click(function(event) {
            var form = $(this).closest("form");
            event.preventDefault();

            swal({
                    title: 'Apakah Anda yakin?',
                    text: 'Tindakan ini tidak dapat dibatalkan',
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

        // Add mobile-specific styles
        $(document).ready(function() {
            // On smaller screens, adjust button sizing
            if (window.innerWidth < 768) {
                $('.d-flex.d-md-none .btn').addClass('btn-sm');
            }
        });
    </script>
@endpush

@push('style')
    <style>
        /* Responsive button styles */
        @media (max-width: 767.98px) {
            .d-flex.d-md-none .btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.9rem;
            }

            .table-responsive {
                overflow-x: auto;
            }

            /* Ensure text doesn't overflow on small screens */
            .btn i+span {
                max-width: 100px;
                display: inline-block;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        }
    </style>
@endpush
