@extends('layouts.app')

@section('title', 'Edit Bahan Baku')

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
                <h1>Edit Bahan Baku</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Inventaris</a></div>
                    <div class="breadcrumb-item">Edit Bahan Baku</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Edit Bahan Baku</h2>
                <p class="section-lead">
                    Perbarui informasi untuk bahan baku: {{ $rawMaterial->name }}
                </p>

                <div class="row">
                    <div class="col-12 col-md-12 col-lg-12">
                        <div class="card">
                            <form action="{{ route('raw-materials.update', $rawMaterial->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="card-header">
                                    <h4>Informasi Bahan Baku</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Nama <span class="text-danger">*</span></label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $rawMaterial->name) }}" required>
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Satuan <span class="text-danger">*</span></label>
                                        <div class="col-sm-12 col-md-7">
                                            <select class="form-control selectric @error('unit') is-invalid @enderror" name="unit" required>
                                                <option value="" disabled>Pilih satuan</option>
                                                <option value="Ball" {{ old('unit', $rawMaterial->unit) == 'Ball' ? 'selected' : '' }}>Ball</option>
                                                <option value="Kg" {{ old('unit', $rawMaterial->unit) == 'Kg' ? 'selected' : '' }}>Kg</option>
                                                <option value="Bks" {{ old('unit', $rawMaterial->unit) == 'Bks' ? 'selected' : '' }}>Bks</option>
                                                <option value="Ikat" {{ old('unit', $rawMaterial->unit) == 'Ikat' ? 'selected' : '' }}>Ikat</option>
                                                <option value="Pcs" {{ old('unit', $rawMaterial->unit) == 'Pcs' ? 'selected' : '' }}>Pcs</option>
                                                <option value="Dus" {{ old('unit', $rawMaterial->unit) == 'Dus' ? 'selected' : '' }}>Dus</option>
                                                <option value="Pack" {{ old('unit', $rawMaterial->unit) == 'Pack' ? 'selected' : '' }}>Pack</option>
                                                <option value="Renteng" {{ old('unit', $rawMaterial->unit) == 'Renteng' ? 'selected' : '' }}>Renteng</option>
                                                <option value="Botol" {{ old('unit', $rawMaterial->unit) == 'Botol' ? 'selected' : '' }}>Botol</option>
                                                <option value="Slop" {{ old('unit', $rawMaterial->unit) == 'Slop' ? 'selected' : '' }}>Slop</option>
                                                <option value="Box" {{ old('unit', $rawMaterial->unit) == 'Box' ? 'selected' : '' }}>Box</option>
                                                <option value="Peti" {{ old('unit', $rawMaterial->unit) == 'Peti' ? 'selected' : '' }}>Peti</option>
                                            </select>
                                            @error('unit')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Harga Jual (Rp) <span class="text-danger">*</span></label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="number" class="form-control @error('price') is-invalid @enderror" name="price" value="{{ old('price', $rawMaterial->price) }}" required>
                                            @error('price')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="form-text text-muted">Harga jual ke outlet dalam Rupiah (Rp).</small>
                                        </div>
                                    </div>

                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Harga Beli (Rp) <span class="text-danger">*</span></label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="number" class="form-control @error('purchase_price') is-invalid @enderror" name="purchase_price" value="{{ old('purchase_price', $rawMaterial->purchase_price) }}" required>
                                            @error('purchase_price')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="form-text text-muted">Harga beli dari supplier dalam Rupiah (Rp).</small>

                                            @if($rawMaterial->price > 0 && $rawMaterial->purchase_price > 0)
                                                @php
                                                    $margin = $rawMaterial->price - $rawMaterial->purchase_price;
                                                    $marginPercentage = ($margin / $rawMaterial->purchase_price) * 100;
                                                    $marginClass = $margin >= 0 ? 'text-success' : 'text-danger';
                                                @endphp
                                                <div id="margin-info" class="mt-2">
                                                    <small class="{{ $marginClass }}">
                                                        Margin: Rp {{ number_format($margin, 0, ',', '.') }}
                                                        ({{ number_format($marginPercentage, 2) }}%)
                                                    </small>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Stok Saat Ini <span class="text-danger">*</span></label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="number" class="form-control @error('stock') is-invalid @enderror" name="stock" value="{{ old('stock', $rawMaterial->stock) }}" required>
                                            @error('stock')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                            <small class="form-text text-muted">Anda juga dapat menyesuaikan stok dari halaman daftar.</small>
                                        </div>
                                    </div>

                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Deskripsi</label>
                                        <div class="col-sm-12 col-md-7">
                                            <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="4">{{ old('description', $rawMaterial->description) }}</textarea>
                                            @error('description')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Status</label>
                                        <div class="col-sm-12 col-md-7">
                                            <div class="selectgroup w-100">
                                                <label class="selectgroup-item">
                                                    <input type="radio" name="is_active" value="1" class="selectgroup-input" {{ old('is_active', $rawMaterial->is_active) == 1 ? 'checked' : '' }}>
                                                    <span class="selectgroup-button">Aktif</span>
                                                </label>
                                                <label class="selectgroup-item">
                                                    <input type="radio" name="is_active" value="0" class="selectgroup-input" {{ old('is_active', $rawMaterial->is_active) == 0 ? 'checked' : '' }}>
                                                    <span class="selectgroup-button">Tidak Aktif</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <a href="{{ route('raw-materials.index') }}" class="btn btn-secondary mr-2">Batal</a>
                                    <button type="submit" class="btn btn-primary">Perbarui</button>
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
        // Calculate margin when price or purchase_price changes
        $(document).ready(function() {
            function calculateMargin() {
                var price = parseFloat($('input[name="price"]').val()) || 0;
                var purchasePrice = parseFloat($('input[name="purchase_price"]').val()) || 0;

                if (purchasePrice > 0) {
                    var marginAmount = price - purchasePrice;
                    var marginPercentage = (marginAmount / purchasePrice) * 100;

                    // Format values
                    var formattedMargin = marginAmount.toLocaleString('id-ID');
                    var formattedPercentage = marginPercentage.toFixed(2);

                    // Display margin info
                    var marginInfo = formattedMargin + ' (' + formattedPercentage + '%)';

                    // If margin info element doesn't exist, create it
                    if ($('#margin-info').length === 0) {
                        $('input[name="purchase_price"]').parent().append('<div id="margin-info" class="mt-2"></div>');
                    }

                    // Update margin info with appropriate color
                    var color = marginAmount >= 0 ? 'text-success' : 'text-danger';
                    $('#margin-info').html('<small class="' + color + '">Margin: Rp ' + marginInfo + '</small>');
                }
            }

            // Calculate on input
            $('input[name="price"], input[name="purchase_price"]').on('input', calculateMargin);
        });
    </script>
@endpush
