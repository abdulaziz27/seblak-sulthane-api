@extends('layouts.app')

@section('title', 'Raw Materials')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Raw Materials</h1>
                <div class="section-header-button">
                    <a href="{{ route('raw-materials.create') }}" class="btn btn-primary">Add New</a>
                    <button type="button" class="btn btn-success ml-2" data-toggle="modal" data-target="#importModal">
                        Import Excel
                    </button>
                    <a href="{{ route('raw-materials.export') }}" class="btn btn-info ml-2">
                        Export Excel
                    </a>
                </div>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventory</a></div>
                    <div class="breadcrumb-item">Raw Materials</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Raw Materials List</h4>
                                <div class="card-header-form">
                                    <form method="GET" action="{{ route('raw-materials.index') }}">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Search by name"
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
                                                <th>Name</th>
                                                <th>Unit</th>
                                                <th>Price</th>
                                                <th>Stock</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                <th>Action</th>
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
                                                            {{ $material->is_active ? 'Active' : 'Inactive' }}
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
                                                                    data-confirm="Are you sure?|This action cannot be undone.">
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

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Raw Materials</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('raw-materials.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Excel File</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Instructions:</h6>
                            <ol>
                                <li>Download template <a href="{{ route('raw-materials.template') }}">here</a></li>
                                <li>Fill in the data according to the template</li>
                                <li>Save and upload the file</li>
                            </ol>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Modals -->
    @foreach ($materials as $material)
    <div class="modal fade" id="stockModal{{ $material->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        Adjust Stock: {{ $material->name }}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('raw-materials.update-stock', $material->id) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Current Stock</label>
                            <input type="text" class="form-control"
                                value="{{ $material->stock }} {{ $material->unit }}"
                                disabled>
                        </div>
                        <div class="form-group">
                            <label>Adjustment (positive to add, negative to subtract)</label>
                            <input type="number" class="form-control"
                                name="adjustment" required>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                            data-dismiss="modal">Cancel</button>
                        <button type="submit"
                            class="btn btn-primary">Update Stock</button>
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
        console.log('Scripts loaded');

        // Debug Bootstrap modal
        $(document).ready(function() {
            console.log('Document ready');

            // Manually initialize all modals
            $('.modal').modal({
                show: false
            });

            // Debug modal button clicks
            $('[data-toggle="modal"]').on('click', function() {
                console.log('Modal button clicked');
                var target = $(this).data('target');
                console.log('Target modal:', target);
                $(target).modal('show');
            });

            // Debug modal events
            $('.modal').on('show.bs.modal', function() {
                console.log('Modal show event fired', this.id);
            }).on('shown.bs.modal', function() {
                console.log('Modal shown event fired', this.id);
            }).on('hide.bs.modal', function() {
                console.log('Modal hide event fired', this.id);
            }).on('hidden.bs.modal', function() {
                console.log('Modal hidden event fired', this.id);
                $(this).find('form')[0].reset();
            });
        });

        // Existing scripts below...
        $('.stock-adjust-btn').click(function() {
            var id = $(this).data('id');
            console.log('Trying to show modal for ID:', id);

            // Hapus backdrop yang mungkin tertinggal
            $('.modal-backdrop').remove();

            // Tampilkan modal yang benar
            $('#stockModal' + id).modal('show');
        });

        // Confirm delete functionality
        $('.confirm-delete').click(function(e) {
            var form = $(this).closest('form');
            e.preventDefault();

            swal({
                    title: 'Are you sure?',
                    text: 'Once deleted, you will not be able to recover this item!',
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
