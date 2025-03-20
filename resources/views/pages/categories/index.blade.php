@extends('layouts.app')

@section('title', 'Kategori')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Kategori</h1>
                <div class="section-header-button d-none d-md-flex">
                    <a href="{{ route('categories.create') }}" class="btn btn-primary">Tambah Baru</a>
                    <button type="button" class="btn btn-success ml-2" data-toggle="modal" data-target="#importModal">
                        Import Excel
                    </button>
                    <a href="{{ route('categories.export') }}" class="btn btn-info ml-2">
                        Export Excel
                    </a>
                    <button type="button" class="btn btn-warning ml-2" data-toggle="modal" data-target="#bulkUpdateModal">
                        Update Massal
                    </button>
                    <button type="button" class="btn btn-danger ml-2" data-toggle="modal" data-target="#deleteAllModal">
                        Hapus Semua
                    </button>
                </div>
            </div>

            <div class="section-body">
                <!-- Responsive action buttons for small screens -->
                <div class="d-flex d-md-none mb-4 flex-wrap justify-content-center">
                    <a href="{{ route('categories.create') }}" class="btn btn-primary m-1">
                        <i class="fas fa-plus mr-1"></i> Tambah
                    </a>
                    <button type="button" class="btn btn-success m-1" data-toggle="modal" data-target="#importModal">
                        <i class="fas fa-file-import mr-1"></i> Import
                    </button>
                    <a href="{{ route('categories.export') }}" class="btn btn-info m-1">
                        <i class="fas fa-file-export mr-1"></i> Export
                    </a>
                    <button type="button" class="btn btn-warning m-1" data-toggle="modal" data-target="#bulkUpdateModal">
                        <i class="fas fa-sync-alt mr-1"></i> Update
                    </button>
                    <button type="button" class="btn btn-danger m-1" data-toggle="modal" data-target="#deleteAllModal">
                        <i class="fas fa-trash mr-1"></i> Hapus
                    </button>
                </div>

                @include('layouts.alert')

                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="float-right">
                                    <form method="GET" action="{{ route('categories.index') }}">
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
                                            <th>Deskripsi</th>
                                            <th>Dibuat Pada</th>
                                            <th>Aksi</th>
                                        </tr>
                                        @foreach ($categories as $category)
                                            <tr>
                                                <td>{{ $category->name }}</td>
                                                <td>{{ $category->description ?? '-' }}</td>
                                                <td>{{ $category->created_at }}</td>
                                                <td>
                                                    <div class="d-flex justify-content-center">
                                                        <a href='{{ route('categories.edit', $category->id) }}'
                                                            class="btn btn-sm btn-info btn-icon">
                                                            <i class="fas fa-edit"></i>
                                                            Ubah
                                                        </a>

                                                        <form action="{{ route('categories.destroy', $category->id) }}"
                                                            method="POST" class="ml-2">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="btn btn-sm btn-danger btn-icon confirm-delete">
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
                                    {{ $categories->withQueryString()->links() }}
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
                    <h5 class="modal-title" id="importModalLabel">Import Kategori</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('categories.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>File Excel</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Petunjuk:</h6>
                            <ol>
                                <li>Unduh template <a href="{{ route('categories.template') }}"
                                        class="font-weight-bold text-primary">disini</a></li>
                                <li>Isi data kategori sesuai template</li>
                                <li>Simpan dan unggah file</li>
                            </ol>
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

    <!-- Delete All Modal -->
    <div class="modal fade" id="deleteAllModal" tabindex="-1" role="dialog" aria-labelledby="deleteAllModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteAllModalLabel">Hapus Semua Kategori</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus semua kategori? Tindakan ini tidak dapat dibatalkan.</p>
                    <p class="text-danger"><strong>Peringatan: Ini akan secara permanen menghapus semua kategori dari
                            database!</strong></p>
                </div>
                <div class="modal-footer">
                    <form action="{{ route('categories.deleteAll') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus Semua Kategori</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Update Modal -->
    <div class="modal fade" id="bulkUpdateModal" tabindex="-1" role="dialog" aria-labelledby="bulkUpdateModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkUpdateModalLabel">Update Massal Kategori</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('categories.bulkUpdate') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>File Excel</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Petunjuk:</h6>
                            <ol>
                                <li>Unduh template update <a href="{{ route('categories.exportForUpdate') }}"
                                        class="font-weight-bold text-primary">disini</a></li>
                                <li>Update data kategori sesuai template</li>
                                <li>Simpan dan unggah file</li>
                            </ol>
                            <p>Urutan kolom: ID, Nama</p>
                            <p class="text-warning">Catatan: ID kategori tidak boleh diubah karena digunakan sebagai
                                referensi untuk update</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">Update Kategori</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- JS Libraries -->
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>

    <!-- SweetAlert Library -->
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

    <!-- Page Specific JS File -->
    <script src="{{ asset('js/page/features-posts.js') }}"></script>

    <script>
        // Add mobile-specific styles
        $(document).ready(function() {
            // On smaller screens, adjust button sizing
            if (window.innerWidth < 768) {
                $('.d-flex.d-md-none .btn').addClass('btn-sm');
            }

            // Confirm delete functionality
            $('.confirm-delete').click(function(e) {
                e.preventDefault();
                var form = $(this).closest('form');

                swal({
                        title: 'Apakah Anda yakin?',
                        text: 'Tindakan ini tidak dapat dibatalkan',
                        icon: 'warning',
                        buttons: true,
                        dangerMode: true,
                    })
                    .then(function(willDelete) {
                        if (willDelete) {
                            form.submit();
                        }
                    });
            });
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
