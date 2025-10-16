@extends('layouts.app')

@section('title', 'Daftar Produk')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Manajemen Produk</h1>
                @if(Auth::user()->role !== 'staff')
                <div class="section-header-button d-none d-md-flex">
                    <a href="{{ route('products.create') }}" class="btn btn-primary">Tambah Produk Baru</a>
                    <button type="button" class="btn btn-success ml-2" data-toggle="modal" data-target="#importModal">
                        Import Excel
                    </button>
                    <a href="{{ route('products.export') }}" class="btn btn-info ml-2">
                        Export Excel
                    </a>
                    <button type="button" class="btn btn-warning ml-2" data-toggle="modal" data-target="#bulkUpdateModal">
                        Update Massal
                    </button>
                    <button type="button" class="btn btn-danger ml-2" data-toggle="modal" data-target="#deleteAllModal">
                        Hapus Semua Produk
                    </button>
                </div>
                @endif
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Produk</a></div>
                    <div class="breadcrumb-item">Semua Produk</div>
                </div>
            </div>
            <div class="section-body">
                <!-- Responsive action buttons for small screens -->
                @if(Auth::user()->role !== 'staff')
                <div class="d-flex d-md-none mb-4 flex-wrap justify-content-center">
                    <a href="{{ route('products.create') }}" class="btn btn-primary m-1">
                        <i class="fas fa-plus mr-1"></i> Tambah
                    </a>
                    <button type="button" class="btn btn-success m-1" data-toggle="modal" data-target="#importModal">
                        <i class="fas fa-file-import mr-1"></i> Import
                    </button>
                    <a href="{{ route('products.export') }}" class="btn btn-info m-1">
                        <i class="fas fa-file-export mr-1"></i> Export
                    </a>
                    <button type="button" class="btn btn-warning m-1" data-toggle="modal" data-target="#bulkUpdateModal">
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
                            <div class="card-header">
                                <h4>Daftar Semua Produk</h4>
                            </div>
                            <div class="card-body">
                                <div class="float-right">
                                    <form method="GET" action="{{ route('products.index') }}">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Cari produk..."
                                                name="name">
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
                                            <th>Gambar</th>
                                            <th>Nama Produk</th>
                                            <th>Kategori</th>
                                            <th>Harga</th>
                                            <th>Status</th>
                                            <th>Dibuat Pada</th>
                                            <th>Aksi</th>
                                        </tr>
                                        @foreach ($products as $product)
                                            <tr>
                                                <td>
                                                    @if ($product->image)
                                                        <img src="{{ asset($product->image) }}" alt="{{ $product->name }}" 
                                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
                                                    @else
                                                        <div style="width: 50px; height: 50px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                            <i class="fas fa-image text-muted"></i>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $product->name }}
                                                    @if ($product->is_favorite)
                                                        <i class="fas fa-star text-warning ml-1" title="Produk Favorit"></i>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $product->category->name }}
                                                </td>
                                                <td>
                                                    Rp {{ number_format($product->price, 0, ',', '.') }}
                                                </td>
                                                <td>
                                                    @if ($product->status == 1)
                                                        <span class="badge badge-success">Aktif</span>
                                                    @else
                                                        <span class="badge badge-danger">Tidak Aktif</span>
                                                    @endif
                                                </td>
                                                <td>{{ $product->created_at->format('d M Y H:i') }}</td>
                                                <td>
                                                    @if (Auth::user()->role !== 'staff')
                                                        <div class="d-flex justify-content-center">
                                                            <a href='{{ route('products.edit', $product->id) }}'
                                                                class="btn btn-sm btn-info btn-icon">
                                                                <i class="fas fa-edit"></i>
                                                                Edit
                                                            </a>

                                                            <form action="{{ route('products.destroy', $product->id) }}"
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
                                                    @else
                                                        <span class="badge badge-secondary">Lihat Saja</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>
                                <div class="float-right">
                                    {{ $products->withQueryString()->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Import Modal --}}
    <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Produk dari Excel</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Pilih File Excel</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Petunjuk:</h6>
                            <ol>
                                <li>Unduh template <a href="{{ route('products.template') }}"
                                        class="font-weight-bold text-primary">disini</a></li>
                                <li>Isi data produk sesuai dengan templatenya</li>
                                <li>Simpan dan unggah filenya</li>
                            </ol>
                            <p>Urutan kolom: Nama, Kategori, Deskripsi, Harga, Stok</p>
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
                    <h5 class="modal-title text-danger" id="deleteAllModalLabel">Arsipkan Semua Produk</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin mengarsipkan semua produk? Produk akan disembunyikan tetapi data tetap
                        tersimpan.</p>
                    <p class="text-warning"><strong>Catatan: Produk yang sudah pernah digunakan dalam transaksi akan tetap
                            tersimpan.</strong></p>
                </div>
                <div class="modal-footer">
                    <form id="delete-all-form" action="{{ route('products.deleteAll') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger delete-all-btn">Arsipkan Semua Produk</button>
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
                    <h5 class="modal-title" id="bulkUpdateModalLabel">Update Massal Produk</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('products.bulkUpdate') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Pilih File Excel</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Petunjuk:</h6>
                            <ol>
                                <li>Unduh template update <a href="{{ route('products.exportForUpdate') }}"
                                        class="font-weight-bold text-primary">disini</a>
                                </li>
                                <li>Update data produk sesuai dengan templatenya</li>
                                <li>Simpan dan unggah filenya</li>
                            </ol>
                            <p>Urutan kolom: ID, Nama, Kategori, Deskripsi, Harga, Stok, Status, Favorit</p>
                            <p class="text-warning">Catatan: ID produk tidak boleh diubah karena digunakan sebagai
                                referensi
                                untuk update</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">Update Produk</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- JS Libraies -->
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>

    <!-- Page Specific JS File -->
    <script src="{{ asset('js/page/features-posts.js') }}"></script>

    <script>
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
