@extends('layouts.app')

@section('title', 'Edit Material Order')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/select2/dist/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-tagsinput/dist/bootstrap-tagsinput.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Edit Material Order</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventory</a></div>
                    <div class="breadcrumb-item">Edit Material Order</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Edit Material Order #{{ $materialOrder->id }}</h2>
                <p class="section-lead">
                    Modify your material order here. Only pending orders can be edited.
                </p>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Order Information</h4>
                            </div>
                            <form action="{{ route('material-orders.update', $materialOrder->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="card-body">
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Outlet <span
                                                class="text-danger">*</span></label>
                                        <div class="col-sm-12 col-md-7">
                                            @if (Auth::user()->role === 'owner')
                                                <select
                                                    class="form-control select2 @error('franchise_id') is-invalid @enderror"
                                                    name="franchise_id" required>
                                                    <option value="">Select Outlet</option>
                                                    @foreach ($outlets as $outlet)
                                                        <option value="{{ $outlet->id }}"
                                                            {{ old('franchise_id', $materialOrder->franchise_id) == $outlet->id ? 'selected' : '' }}>
                                                            {{ $outlet->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="text" class="form-control"
                                                    value="{{ Auth::user()->outlet->name }}" disabled>
                                                <input type="hidden" name="franchise_id"
                                                    value="{{ Auth::user()->outlet_id }}">
                                            @endif
                                            @error('franchise_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Payment Method
                                            <span class="text-danger">*</span></label>
                                        <div class="col-sm-12 col-md-7">
                                            <select
                                                class="form-control selectric @error('payment_method') is-invalid @enderror"
                                                name="payment_method" required>
                                                <option value="">Select Payment Method</option>
                                                @foreach($paymentMethods as $value => $label)
                                                    <option value="{{ $value }}"
                                                        {{ old('payment_method', $materialOrder->payment_method) == $value ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('payment_method')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Notes</label>
                                        <div class="col-sm-12 col-md-7">
                                            <textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="3">{{ old('notes', $materialOrder->notes) }}</textarea>
                                            @error('notes')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Order Items
                                            <span class="text-danger">*</span></label>
                                        <div class="col-sm-12 col-md-7">
                                            <div class="materials-container">
                                                @php $index = 0; @endphp
                                                @foreach($materialOrder->items as $item)
                                                    <div class="row mb-3 material-row">
                                                        <div class="col-md-6">
                                                            <select
                                                                class="form-control select2 material-select @error('materials.' . $index . '.raw_material_id') is-invalid @enderror"
                                                                name="materials[{{ $index }}][raw_material_id]"
                                                                required>
                                                                <option value="">Select Material</option>
                                                                @foreach ($rawMaterials as $rawMaterial)
                                                                    <option value="{{ $rawMaterial->id }}"
                                                                        data-price="{{ $rawMaterial->price }}"
                                                                        {{ $item->raw_material_id == $rawMaterial->id ? 'selected' : '' }}>
                                                                        {{ $rawMaterial->name }}
                                                                        ({{ $rawMaterial->unit }}) - Rp
                                                                        {{ number_format($rawMaterial->price, 0, ',', '.') }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            @error('materials.' . $index . '.raw_material_id')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="number"
                                                                class="form-control quantity-input @error('materials.' . $index . '.quantity') is-invalid @enderror"
                                                                name="materials[{{ $index }}][quantity]"
                                                                placeholder="Quantity"
                                                                value="{{ old('materials.' . $index . '.quantity', $item->quantity) }}" min="1"
                                                                required>
                                                            @error('materials.' . $index . '.quantity')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                        <div class="col-md-2">
                                                            @if ($index > 0)
                                                                <button type="button"
                                                                    class="btn btn-danger remove-material"><i
                                                                        class="fas fa-times"></i></button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @php $index++; @endphp
                                                @endforeach

                                                @if (count($materialOrder->items) === 0)
                                                    <div class="row mb-3 material-row">
                                                        <div class="col-md-6">
                                                            <select class="form-control select2 material-select"
                                                                name="materials[0][raw_material_id]" required>
                                                                <option value="">Select Material</option>
                                                                @foreach ($rawMaterials as $rawMaterial)
                                                                    <option value="{{ $rawMaterial->id }}"
                                                                        data-price="{{ $rawMaterial->price }}">
                                                                        {{ $rawMaterial->name }}
                                                                        ({{ $rawMaterial->unit }}) - Rp
                                                                        {{ number_format($rawMaterial->price, 0, ',', '.') }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="number" class="form-control quantity-input"
                                                                name="materials[0][quantity]" placeholder="Quantity"
                                                                min="1" required>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <!-- No remove button for first row -->
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            <button type="button" class="btn btn-info btn-sm mt-2" id="add-material">
                                                <i class="fas fa-plus"></i> Add Material
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <a href="{{ route('material-orders.show', $materialOrder) }}"
                                        class="btn btn-secondary mr-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Update Order</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <!-- JS Libraies -->
    <script src="{{ asset('library/cleave.js/dist/cleave.min.js') }}"></script>
    <script src="{{ asset('library/cleave.js/dist/addons/cleave-phone.us.js') }}"></script>
    <script src="{{ asset('library/bootstrap-daterangepicker/daterangepicker.js') }}"></script>
    <script src="{{ asset('library/bootstrap-colorpicker/dist/js/bootstrap-colorpicker.min.js') }}"></script>
    <script src="{{ asset('library/bootstrap-timepicker/js/bootstrap-timepicker.min.js') }}"></script>
    <script src="{{ asset('library/bootstrap-tagsinput/dist/bootstrap-tagsinput.min.js') }}"></script>
    <script src="{{ asset('library/select2/dist/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>

    <!-- Page Specific JS File -->
    <script src="{{ asset('js/page/forms-advanced-forms.js') }}"></script>

    <script>
        $(document).ready(function() {
            // Initialize select2
            $('.select2').select2();

            // Add material row
            $('#add-material').click(function() {
                var index = $('.material-row').length;

                var newRow = `
                    <div class="row mb-3 material-row">
                        <div class="col-md-6">
                            <select class="form-control select2 material-select" name="materials[${index}][raw_material_id]" required>
                                <option value="">Select Material</option>
                                @foreach ($rawMaterials as $rawMaterial)
                                    <option value="{{ $rawMaterial->id }}" data-price="{{ $rawMaterial->price }}">
                                        {{ $rawMaterial->name }} ({{ $rawMaterial->unit }}) - Rp {{ number_format($rawMaterial->price, 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="number" class="form-control quantity-input" name="materials[${index}][quantity]" placeholder="Quantity" min="1" required>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger remove-material"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                `;

                $('.materials-container').append(newRow);
                $('.select2').select2();
            });

            // Remove material row
            $(document).on('click', '.remove-material', function() {
                $(this).closest('.material-row').remove();

                // Reindex the remaining rows
                $('.material-row').each(function(newIndex) {
                    var row = $(this);
                    row.find('select.material-select').attr('name',
                        `materials[${newIndex}][raw_material_id]`);
                    row.find('input.quantity-input').attr('name',
                        `materials[${newIndex}][quantity]`);
                });
            });
        });
    </script>
@endpush
