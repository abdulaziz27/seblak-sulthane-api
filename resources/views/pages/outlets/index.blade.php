@extends('layouts.app')

@section('title', 'Outlets')

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
                    <div class="section-header-button">
                        <a href="{{ route('outlets.create') }}" class="btn btn-primary">Add New</a>
                        <button type="button" class="btn btn-success ml-2" data-toggle="modal" data-target="#importModal">
                            Import Excel
                        </button>
                        <a href="{{ route('outlets.export') }}" class="btn btn-info ml-2">
                            Export Excel
                        </a>
                        <button type="button" class="btn btn-warning ml-2" data-toggle="modal" data-target="#bulkUpdateModal">
                            Bulk Update
                        </button>
                        <button type="button" class="btn btn-danger ml-2" data-toggle="modal" data-target="#deleteAllModal">
                            Delete All
                        </button>
                    </div>
                @endif
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Outlets</a></div>
                    <div class="breadcrumb-item">All Outlets</div>
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
                                    <form method="GET" action="{{ route('outlets.index') }}">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Search" name="name">
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
                                            <th>Name</th>
                                            <th>Address</th>
                                            <th>Phone</th>
                                            <th>Leader</th>
                                            <th>Created At</th>
                                            @if (Auth::user()->role === 'owner')
                                                <th>Action</th>
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
                                                <td>{{ $outlet->created_at->format('d M Y') }}</td>
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
                                                                <button class="btn btn-sm btn-danger btn-icon confirm-delete">
                                                                    <i class="fas fa-times"></i> Delete
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
                    <h5 class="modal-title" id="importModalLabel">Import Outlets</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('outlets.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Excel File</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Instructions:</h6>
                            <ol>
                                <li>Download template <a href="{{ route('outlets.template') }}">here</a></li>
                                <li>Fill in the data according to the template</li>
                                <li>Save and upload the file</li>
                            </ol>
                            <p>Column order: NAMA OUTLET, ALAMAT 1, ALAMAT 2, NO. TELP, PIMPINAN, KET</p>
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

    <!-- Bulk Update Modal -->
    <div class="modal fade" id="bulkUpdateModal" tabindex="-1" role="dialog" aria-labelledby="bulkUpdateModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkUpdateModalLabel">Bulk Update Outlets</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('outlets.bulkUpdate') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Excel File</label>
                            <input type="file" class="form-control" name="file" accept=".xlsx, .xls" required>
                        </div>
                        <div class="alert alert-info">
                            <h6>Instructions:</h6>
                            <ol>
                                <li>Download the update template <a href="{{ route('outlets.exportForUpdate') }}">here</a></li>
                                <li>Update the data as needed</li>
                                <li>Save and upload the file</li>
                            </ol>
                            <p>Column order: ID, NAMA OUTLET, ALAMAT 1, ALAMAT 2, NO. TELP, PIMPINAN, KET</p>
                            <p class="text-warning"><strong>Note:</strong> Do not modify the ID column as it's used as a reference for updates.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Update Outlets</button>
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
                    <h5 class="modal-title text-danger" id="deleteAllModalLabel">Delete All Outlets</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete all outlets? This action cannot be undone.</p>
                    <p class="text-danger"><strong>Warning:</strong> This will permanently delete all outlets that don't have associated users or orders.</p>
                </div>
                <div class="modal-footer">
                    <form action="{{ route('outlets.deleteAll') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete All Outlets</button>
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
                title: 'Are you sure?',
                text: 'This action cannot be undone',
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
