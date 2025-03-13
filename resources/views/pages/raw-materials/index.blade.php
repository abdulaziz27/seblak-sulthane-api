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
                <div class="section-header-button">
                    <a href="{{ route('raw-materials.create') }}" class="btn btn-primary">Tambah Baru</a>
                    <button type="button" class="btn btn-success ml-2" data-toggle="modal" data-target="#importModal">
                        Import Excel
                    </button>
                    <a href="{{ route('raw-materials.export') }}" class="btn btn-info ml-2">
                        Eksport Excel
                    </a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventaris</a></div>
                    <div class="breadcrumb-item">Bahan Baku</div>
                </div>
            </div>

            <div class="section-body">
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
                                <li>Unduh template <a href="{{ route('raw-materials.template') }}" class="font-weight-bold text-primary">disini</a></li>
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
                                value="{{ $material->stock }} {{ $material->unit }}"
                                disabled>
                        </div>
                        <div class="form-group">
                            <label>Penyesuaian (positif untuk menambah, negatif untuk mengurangi)</label>
                            <input type="number" class="form-control"
                                name="adjustment" required>
                        </div>
                        <div class="form-group">
                            <label>Catatan</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                            data-dismiss="modal">Batal</button>
                        <button type="submit"
                            class="btn btn-primary">Perbarui Stok</button>
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
