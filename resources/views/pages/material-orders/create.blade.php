@extends('layouts.app')

@section('title', 'Tambah Pesanan Bahan')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/bootstrap-daterangepicker/daterangepicker.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/select2/dist/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/selectric/public/selectric.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-timepicker/css/bootstrap-timepicker.min.css') }}">
    <link rel="stylesheet" href="{{ asset('library/bootstrap-tagsinput/dist/bootstrap-tagsinput.css') }}">
    <style>
        .stock-normal {
            color: #28a745;
            font-weight: bold;
        }

        .stock-medium {
            color: #ffc107;
            font-weight: bold;
        }

        .stock-low {
            color: #dc3545;
            font-weight: bold;
        }

        .select2-container--default .select2-results__option {
            padding: 8px 12px;
        }

        .stock-indicator {
            display: inline-block;
            padding: 2px 6px;
            margin-left: 8px;
            border-radius: 4px;
            font-size: 0.8em;
        }

        .material-details-container {
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 4px;
            background-color: #f9f9f9;
            display: none;
        }
    </style>
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Tambah Pesanan Bahan</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventaris</a></div>
                    <div class="breadcrumb-item">Tambah Pesanan Bahan</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Tambah Pesanan Bahan</h2>
                <p class="section-lead">
                    Buat pesanan bahan baru. Level stok saat ini ditampilkan untuk membantu keputusan pemesanan.
                </p>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Informasi Pesanan</h4>
                            </div>
                            <form action="{{ route('material-orders.store') }}" method="POST">
                                @csrf
                                <div class="card-body">
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Outlet <span
                                                class="text-danger">*</span></label>
                                        <div class="col-sm-12 col-md-7">
                                            @if (Auth::user()->role === 'owner')
                                                <select
                                                    class="form-control select2 @error('franchise_id') is-invalid @enderror"
                                                    name="franchise_id" required>
                                                    <option value="">Pilih Outlet</option>
                                                    @foreach ($outlets as $outlet)
                                                        <option value="{{ $outlet->id }}"
                                                            {{ old('franchise_id') == $outlet->id ? 'selected' : '' }}>
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
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Metode
                                            Pembayaran
                                            <span class="text-danger">*</span></label>
                                        <div class="col-sm-12 col-md-7">
                                            <select
                                                class="form-control selectric @error('payment_method') is-invalid @enderror"
                                                name="payment_method" required>
                                                <option value="">Pilih Metode Pembayaran</option>
                                                <option value="cash"
                                                    {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Tunai</option>
                                                <option value="bank_transfer"
                                                    {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>
                                                    Transfer Bank</option>
                                                <option value="e-wallet"
                                                    {{ old('payment_method') == 'e-wallet' ? 'selected' : '' }}>E-Wallet
                                                </option>
                                            </select>
                                            @error('payment_method')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Catatan</label>
                                        <div class="col-sm-12 col-md-7">
                                            <textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="3">{{ old('notes') }}</textarea>
                                            @error('notes')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- Form Item Pesanan di create.blade.php -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Item Pesanan
                                            <span class="text-danger">*</span></label>
                                        <div class="col-sm-12 col-md-7">
                                            <div class="materials-container">
                                                @if (old('materials'))
                                                    @foreach (old('materials') as $index => $material)
                                                        <div class="row mb-3 material-row">
                                                            <div class="col-md-6">
                                                                <select
                                                                    class="form-control select2 material-select @error('materials.' . $index . '.raw_material_id') is-invalid @enderror"
                                                                    name="materials[{{ $index }}][raw_material_id]"
                                                                    required>
                                                                    <option value="">Pilih Bahan</option>
                                                                    @foreach ($rawMaterials as $rawMaterial)
                                                                        @php
                                                                            $stockClass = '';
                                                                            if ($rawMaterial->available_stock <= 5) {
                                                                                $stockClass = 'stock-low';
                                                                            } elseif (
                                                                                $rawMaterial->available_stock <= 15
                                                                            ) {
                                                                                $stockClass = 'stock-medium';
                                                                            } else {
                                                                                $stockClass = 'stock-normal';
                                                                            }
                                                                        @endphp
                                                                        <option value="{{ $rawMaterial->id }}"
                                                                            data-price="{{ $rawMaterial->price }}"
                                                                            data-stock="{{ $rawMaterial->stock }}"
                                                                            data-reserved="{{ $rawMaterial->reserved_stock }}"
                                                                            data-available="{{ $rawMaterial->available_stock }}"
                                                                            data-unit="{{ $rawMaterial->unit }}"
                                                                            data-stock-class="{{ $stockClass }}"
                                                                            {{ $material['raw_material_id'] == $rawMaterial->id ? 'selected' : '' }}>
                                                                            {{ $rawMaterial->name }} -
                                                                            Tersedia: {{ $rawMaterial->available_stock }}
                                                                            {{ $rawMaterial->unit }} -
                                                                            Rp
                                                                            {{ number_format($rawMaterial->price, 0, ',', '.') }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                                @error('materials.' . $index . '.raw_material_id')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                                <div class="material-details-container"
                                                                    id="material-details-{{ $index }}">
                                                                    <div class="stock-info"></div>
                                                                    <div class="price-info"></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <input type="number"
                                                                    class="form-control quantity-input @error('materials.' . $index . '.quantity') is-invalid @enderror"
                                                                    name="materials[{{ $index }}][quantity]"
                                                                    placeholder="Jumlah"
                                                                    value="{{ $material['quantity'] }}" min="1"
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
                                                    @endforeach
                                                @else
                                                    <div class="row mb-3 material-row">
                                                        <div class="col-md-6">
                                                            <select class="form-control select2 material-select"
                                                                name="materials[0][raw_material_id]" required>
                                                                <option value="">Pilih Bahan</option>
                                                                @foreach ($rawMaterials as $rawMaterial)
                                                                    @php
                                                                        $stockClass = '';
                                                                        if ($rawMaterial->available_stock <= 5) {
                                                                            $stockClass = 'stock-low';
                                                                        } elseif ($rawMaterial->available_stock <= 15) {
                                                                            $stockClass = 'stock-medium';
                                                                        } else {
                                                                            $stockClass = 'stock-normal';
                                                                        }
                                                                    @endphp
                                                                    <option value="{{ $rawMaterial->id }}"
                                                                        data-price="{{ $rawMaterial->price }}"
                                                                        data-stock="{{ $rawMaterial->stock }}"
                                                                        data-reserved="{{ $rawMaterial->reserved_stock }}"
                                                                        data-available="{{ $rawMaterial->available_stock }}"
                                                                        data-unit="{{ $rawMaterial->unit }}"
                                                                        data-stock-class="{{ $stockClass }}">
                                                                        {{ $rawMaterial->name }} -
                                                                        Tersedia: {{ $rawMaterial->available_stock }}
                                                                        {{ $rawMaterial->unit }} -
                                                                        Rp
                                                                        {{ number_format($rawMaterial->price, 0, ',', '.') }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            <div class="material-details-container"
                                                                id="material-details-0">
                                                                <div class="stock-info"></div>
                                                                <div class="price-info"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="number" class="form-control quantity-input"
                                                                name="materials[0][quantity]" placeholder="Jumlah"
                                                                min="1" required>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <!-- No remove button for first row -->
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            <button type="button" class="btn btn-info btn-sm mt-2" id="add-material">
                                                <i class="fas fa-plus"></i> Tambah Bahan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <a href="{{ route('material-orders.index') }}"
                                        class="btn btn-secondary mr-2">Batal</a>
                                    <button type="submit" class="btn btn-primary">Buat Pesanan</button>
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
    <!-- JS Libraries -->
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
            // Initialize select2 with custom template
            $('.select2').select2({
                templateResult: formatMaterial,
                templateSelection: formatMaterialSelection
            });

            // Format material options in dropdown
            function formatMaterial(material) {
                if (!material.id) {
                    return material.text;
                }

                var $material = $(material.element);
                var stock = $material.data('stock');
                var reserved = $material.data('reserved') || 0;
                var available = $material.data('available');
                var stockClass = $material.data('stock-class');
                var unit = $material.data('unit');

                var $materialOption = $(
                    '<div class="material-option">' +
                    '<div>' + material.text + '</div>' +
                    '<div class="' + stockClass + '">Stok Tersedia: ' + available + ' ' + unit + '</div>' +
                    '<div>Total Stok: ' + stock + ' ' + unit + ' (Direservasi: ' + reserved + ' ' + unit +
                    ')</div>' +
                    '</div>'
                );

                return $materialOption;
            }

            // Format selected material
            function formatMaterialSelection(material) {
                if (!material.id) {
                    return material.text;
                }

                var $material = $(material.element);
                var materialName = $material.text().split(' - ')[0];

                return materialName;
            }

            // Update material details when selection changes
            $(document).on('change', '.material-select', function() {
                var selectedOption = $(this).find('option:selected');
                var rowIndex = $(this).closest('.material-row').index();
                var detailsContainer = $('#material-details-' + rowIndex);

                if (selectedOption.val()) {
                    var stock = selectedOption.data('stock');
                    var reserved = selectedOption.data('reserved') || 0;
                    var available = selectedOption.data('available');
                    var unit = selectedOption.data('unit');
                    var price = selectedOption.data('price');
                    var stockClass = selectedOption.data('stock-class');

                    // Update max attribute on quantity input
                    $(this).closest('.material-row').find('.quantity-input').attr('max', available);

                    // Set content and show details container
                    detailsContainer.find('.stock-info').html(
                        '<div><strong>Stok Total:</strong> ' + stock + ' ' + unit + '</div>' +
                        '<div><strong>Direservasi:</strong> ' + reserved + ' ' + unit + '</div>' +
                        '<div><strong>Stok Tersedia:</strong> <span class="' + stockClass + '">' +
                        available + ' ' + unit + '</span></div>'
                    );
                    detailsContainer.find('.price-info').html('<strong>Harga:</strong> Rp ' + price
                        .toLocaleString('id-ID'));
                    detailsContainer.show();
                } else {
                    detailsContainer.hide();
                }
            });

            // Trigger change event on page load for any pre-selected materials
            $('.material-select').each(function() {
                $(this).trigger('change');
            });

            // Add material row
            $('#add-material').click(function() {
                var index = $('.material-row').length;

                var newRow = `
            <div class="row mb-3 material-row">
                <div class="col-md-6">
                    <select class="form-control select2 material-select" name="materials[${index}][raw_material_id]" required>
                        <option value="">Pilih Bahan</option>
                        @foreach ($rawMaterials as $rawMaterial)
                            @php
                                $stockClass = '';
                                if ($rawMaterial->available_stock <= 5) {
                                    $stockClass = 'stock-low';
                                } elseif ($rawMaterial->available_stock <= 15) {
                                    $stockClass = 'stock-medium';
                                } else {
                                    $stockClass = 'stock-normal';
                                }
                            @endphp
                            <option value="{{ $rawMaterial->id }}"
                                data-price="{{ $rawMaterial->price }}"
                                data-stock="{{ $rawMaterial->stock }}"
                                data-reserved="{{ $rawMaterial->reserved_stock }}"
                                data-available="{{ $rawMaterial->available_stock }}"
                                data-unit="{{ $rawMaterial->unit }}"
                                data-stock-class="{{ $stockClass }}">
                                {{ $rawMaterial->name }} -
                                Tersedia: {{ $rawMaterial->available_stock }} {{ $rawMaterial->unit }} -
                                Rp {{ number_format($rawMaterial->price, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                    <div class="material-details-container" id="material-details-${index}">
                        <div class="stock-info"></div>
                        <div class="price-info"></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control quantity-input" name="materials[${index}][quantity]" placeholder="Jumlah" min="1" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger remove-material"><i class="fas fa-times"></i></button>
                </div>
            </div>
        `;

                $('.materials-container').append(newRow);

                // Initialize select2 for the new row
                $('.material-select').last().select2({
                    templateResult: formatMaterial,
                    templateSelection: formatMaterialSelection
                });
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
                    row.find('.material-details-container').attr('id',
                        `material-details-${newIndex}`);
                });
            });

            // Quantity validation - prevent ordering more than available stock
            $(document).on('input', '.quantity-input', function() {
                var row = $(this).closest('.material-row');
                var selectedOption = row.find('.material-select option:selected');

                if (selectedOption.val()) {
                    var availableStock = parseInt(selectedOption.data('available'));
                    var requestedQuantity = parseInt($(this).val());

                    if (requestedQuantity > availableStock) {
                        $(this).addClass('is-invalid');

                        // Add warning if not already present
                        if (row.find('.stock-warning').length === 0) {
                            row.append(
                                '<div class="col-12 mt-2 stock-warning text-danger">Peringatan: Jumlah yang diminta melebihi stok tersedia!</div>'
                                );
                        }
                    } else {
                        $(this).removeClass('is-invalid');
                        row.find('.stock-warning').remove();
                    }
                }
            });
        });
    </script>
@endpush
