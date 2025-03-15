@extends('layouts.app')

@section('title', 'Bahan Baku')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Bahan Baku</h1>
                <div class="section-header-button d-none d-md-flex">
                    <a href="{{ route('raw-materials.create') }}" class="btn btn-primary">Tambah Baru</a>
                    <button type="button" class="btn btn-success ml-2" data-toggle="modal" data-target="#importModal">
                        Import Excel
                    </button>
                    <a href="{{ route('raw-materials.export') }}" class="btn btn-info ml-2">
                        Ekspor Excel
                    </a>
                    <button type="button" class="btn btn-warning ml-2" data-toggle="modal" data-target="#bulkUpdateModal">
                        Update Massal
                    </button>
                    <button type="button" class="btn btn-danger ml-2" data-toggle="modal" data-target="#deleteAllModal">
                        Hapus Semua
                    </button>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventaris</a></div>
                    <div class="breadcrumb-item">Bahan Baku</div>
                </div>
            </div>

            <div class="section-body">
                <!-- Responsive action buttons for small screens -->
                <div class="d-flex d-md-none mb-4 flex-wrap justify-content-center">
                    <a href="{{ route('raw-materials.create') }}" class="btn btn-primary m-1">
                        <i class="fas fa-plus mr-1"></i> Tambah
                    </a>
                    <button type="button" class="btn btn-success m-1" data-toggle="modal" data-target="#importModal">
                        <i class="fas fa-file-import mr-1"></i> Import
                    </button>
                    <a href="{{ route('raw-materials.export') }}" class="btn btn-info m-1">
                        <i class="fas fa-file-export mr-1"></i> Ekspor
                    </a>
                    <button type="button" class="btn btn-warning m-1" data-toggle="modal" data-target="#bulkUpdateModal">
                        <i class="fas fa-sync-alt mr-1"></i> Update
                    </button>
                    <button type="button" class="btn btn-danger m-1" data-toggle="modal" data-target="#deleteAllModal">
                        <i class="fas fa-trash mr-1"></i> Hapus
                    </button>
                </div>

                @include('layouts.alert')

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Daftar Bahan Baku</h4>
                                <div class="card-header-form">
                                    <form method="GET" action="{{ route('raw-materials.index') }}">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Cari berdasarkan nama"
                                                name="search" value="{{ request('search') }}">
                                            <div class="input-group-btn">
                                                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Informasi Jumlah Bahan Baku -->
                                <div class="mb-4">
                                    <div class="row">
                                        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
                                            <div class="card card-statistic-compact h-100">
                                                <div class="card-body p-0">
                                                    <div class="d-flex align-items-center px-3 py-2">
                                                        <div class="icon-circle bg-primary mr-2">
                                                            <i class="fas fa-boxes"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-1 text-muted font-weight-normal">Total Bahan Baku
                                                            </h6>
                                                            <h4 class="mb-0">{{ $totalCount }}</h4>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
                                            <div class="card card-statistic-compact h-100">
                                                <div class="card-body p-0">
                                                    <div class="d-flex align-items-center px-3 py-2">
                                                        <div class="icon-circle bg-success mr-2">
                                                            <i class="fas fa-check-circle"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-1 text-muted font-weight-normal">Bahan Baku Aktif
                                                            </h6>
                                                            <h4 class="mb-0">{{ $activeCount }}</h4>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
                                            <div class="card card-statistic-compact h-100">
                                                <div class="card-body p-0">
                                                    <div class="d-flex align-items-center px-3 py-2">
                                                        <div class="icon-circle bg-danger mr-2">
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-1 text-muted font-weight-normal">Stok Menipis</h6>
                                                            <h4 class="mb-0">{{ $lowStockCount }}</h4>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
                                            <div class="card card-statistic-compact h-100">
                                                <div class="card-body p-0">
                                                    <div class="d-flex align-items-center px-3 py-2">
                                                        <div class="icon-circle bg-warning mr-2">
                                                            <i class="fas fa-dollar-sign"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-1 text-muted font-weight-normal">Nilai Inventaris
                                                            </h6>
                                                            <h4 class="mb-0 text-nowrap">Rp
                                                                {{ number_format($totalValue, 0, ',', '.') }}</h4>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Informasi Jumlah Bahan Baku -->


                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Nama</th>
                                                <th>Satuan</th>
                                                <th>Harga</th>
                                                <th>Stok</th>
                                                <th>Deskripsi</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($materials as $material)
                                                <tr>
                                                    <td>{{ $material->name }}</td>
                                                    <td>{{ $material->unit }}</td>
                                                    <td>Rp {{ number_format($material->price, 0, ',', '.') }}</td>
                                                    <td>
                                                        <span
                                                            class="badge {{ $material->stock < 10 ? 'badge-danger' : 'badge-success' }}">
                                                            {{ $material->stock }} {{ $material->unit }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $material->description ?? '-' }}</td>
                                                    <td>
                                                        <div
                                                            class="badge badge-{{ $material->is_active ? 'success' : 'danger' }}">
                                                            {{ $material->is_active ? 'Aktif' : 'Nonaktif' }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex">
                                                            <button class="btn btn-sm btn-info mr-1 stock-adjust-btn"
                                                                data-target="#stockModal{{ $material->id }}"
                                                                data-id="{{ $material->id }}">
                                                                <i class="fas fa-boxes"></i>
                                                            </button>
                                                            <a href="{{ route('raw-materials.edit', $material->id) }}"
                                                                class="btn btn-sm btn-primary mr-1">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <form
                                                                action="{{ route('raw-materials.destroy', $material->id) }}"
                                                                method="POST" class="d-inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                    class="btn btn-sm btn-danger confirm-delete"
                                                                    data-confirm="Apakah Anda yakin?|Tindakan ini tidak dapat dibatalkan.">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer">
                                {{ $materials->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Modal Impor -->
    <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Impor Bahan Baku</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('raw-materials.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>File Excel</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Instruksi:</h6>
                            <ol>
                                <li>Unduh template <a href="{{ route('raw-materials.template') }}"
                                        class="font-weight-bold text-primary">disini</a></li>
                                <li>Isi data sesuai dengan template</li>
                                <li>Simpan dan unggah file</li>
                            </ol>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">Impor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Update Massal -->
    <div class="modal fade" id="bulkUpdateModal" tabindex="-1" role="dialog" aria-labelledby="bulkUpdateModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkUpdateModalLabel">Update Massal Bahan Baku</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('raw-materials.bulkUpdate') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>File Excel</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Instruksi:</h6>
                            <ol>
                                <li>Unduh template update <a href="{{ route('raw-materials.exportForUpdate') }}"
                                        class="font-weight-bold text-primary">disini</a></li>
                                <li>Perbarui data sesuai kebutuhan</li>
                                <li>Simpan dan unggah file</li>
                            </ol>
                            <p class="text-warning"><strong>Catatan:</strong> Jangan mengubah kolom ID karena digunakan
                                sebagai referensi untuk pembaruan.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">Update Bahan Baku</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Semua -->
    <div class="modal fade" id="deleteAllModal" tabindex="-1" role="dialog" aria-labelledby="deleteAllModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteAllModalLabel">Hapus Semua Bahan Baku</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus semua bahan baku? Tindakan ini tidak dapat dibatalkan.</p>
                    <p class="text-danger"><strong>Peringatan:</strong> Ini akan secara permanen menghapus semua bahan baku
                        dari database!</p>
                </div>
                <div class="modal-footer">
                    <form action="{{ route('raw-materials.deleteAll') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus Semua Bahan Baku</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Penyesuaian Stok -->
    @foreach ($materials as $material)
        <div class="modal fade" id="stockModal{{ $material->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Sesuaikan Stok: {{ $material->name }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form action="{{ route('raw-materials.update-stock', $material->id) }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Stok Saat Ini</label>
                                <input type="text" class="form-control"
                                    value="{{ $material->stock }} {{ $material->unit }}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Penyesuaian (positif untuk menambah, negatif untuk mengurangi)</label>
                                <input type="number" class="form-control" name="adjustment" required>
                            </div>
                            <div class="form-group">
                                <label>Catatan</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Perbarui Stok</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@push('style')
    <style>
        .modal {
            z-index: 1050;
        }

        .modal-backdrop {
            z-index: 1040;
        }

        .card-statistic-compact {
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .card-statistic-compact .icon-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 14px;
        }

        .card-statistic-compact h6 {
            font-size: 12px;
            margin-bottom: 0;
        }

        .card-statistic-compact h4 {
            font-size: 16px;
            font-weight: 600;
        }

        /* Responsive adjustments */
        @media (min-width: 992px) {
            .card-statistic-compact h4 {
                font-size: 18px;
            }
        }

        @media (max-width: 767.98px) {
            .card-statistic-compact .icon-circle {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .card-statistic-compact h6 {
                font-size: 11px;
            }

            .card-statistic-compact h4 {
                font-size: 15px;
            }

            /* Responsive buttons for mobile view */
            .d-flex.d-md-none .btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.9rem;
            }
        }
    </style>
@endpush

@push('scripts')
    <!-- JS Libraies -->
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>
    <script src="{{ asset('library/sweetalert/dist/sweetalert.min.js') }}"></script>

    <!-- Page Specific JS File -->
    <script src="{{ asset('js/page/features-posts.js') }}"></script>

    <script>
        console.log('Skrip dimuat');

        // Debug Bootstrap modal
        $(document).ready(function() {
            console.log('Dokumen siap');

            // Inisialisasi semua modal secara manual
            $('.modal').modal({
                show: false
            });

            // Debug klik tombol modal
            $('[data-toggle="modal"]').on('click', function() {
                console.log('Tombol modal diklik');
                var target = $(this).data('target');
                console.log('Target modal:', target);
                $(target).modal('show');
            });

            // Debug event modal
            $('.modal').on('show.bs.modal', function() {
                console.log('Event show modal dipicu', this.id);
            }).on('shown.bs.modal', function() {
                console.log('Event shown modal dipicu', this.id);
            }).on('hide.bs.modal', function() {
                console.log('Event hide modal dipicu', this.id);
            }).on('hidden.bs.modal', function() {
                console.log('Event hidden modal dipicu', this.id);
                $(this).find('form')[0].reset();
            });
        });

        // Skrip yang sudah ada di bawah...
        $('.stock-adjust-btn').click(function() {
            var id = $(this).data('id');
            console.log('Mencoba menampilkan modal untuk ID:', id);

            // Hapus backdrop yang mungkin tertinggal
            $('.modal-backdrop').remove();

            // Tampilkan modal yang benar
            $('#stockModal' + id).modal('show');
        });

        // Fungsi konfirmasi hapus
        $('.confirm-delete').click(function(e) {
            var form = $(this).closest('form');
            e.preventDefault();

            swal({
                    title: 'Apakah Anda yakin?',
                    text: 'Setelah dihapus, Anda tidak dapat memulihkan item ini!',
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
