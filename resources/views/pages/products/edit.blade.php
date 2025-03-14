@extends('layouts.app')

@section('title', 'Edit Produk')

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
                <h1>Edit Produk</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Produk</a></div>
                    <div class="breadcrumb-item">Edit Produk</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Edit Produk: {{ $product->name }}</h2>
                <p class="section-lead">Silakan perbarui informasi produk pada form berikut.</p>

                <div class="card">
                    <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="card-header">
                            <h4>Form Edit Produk</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Nama Produk</label>
                                <input type="text"
                                    class="form-control @error('name')
                                is-invalid
                            @enderror"
                                    name="name" value="{{ $product->name }}">
                                @error('name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Deskripsi</label>
                                <input type="text"
                                    class="form-control @error('description')
                                is-invalid
                            @enderror"
                                    name="description" value="{{ $product->description }}">
                                @error('description')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Harga (Rp)</label>
                                <input type="number"
                                    class="form-control @error('price')
                                is-invalid
                            @enderror"
                                    name="price" value="{{ $product->price }}">
                                @error('price')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Stok</label>
                                <input type="number"
                                    class="form-control @error('stock')
                                is-invalid
                            @enderror"
                                    name="stock" value="{{ $product->stock }}">
                                @error('stock')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kategori</label>
                                <select
                                    class="form-control selectric @error('category_id')
                                    is-invalid
                                @enderror"
                                    name="category_id">
                                    <option value="">Pilih Kategori</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ $category->id == $product->category_id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- <div class="form-group">
                                <label class="form-label">Foto Produk</label>
                                <div class="col-sm-9">
                                    <input type="file" class="form-control" name="image"
                                        @error('image') is-invalid @enderror>
                                    <small class="text-muted">Biarkan kosong jika tidak ingin mengubah gambar.</small>

                                    @if ($product->image)
                                    <div class="mt-3">
                                        <p>Gambar saat ini:</p>
                                        <img src="{{ asset($product->image) }}" alt="{{ $product->name }}" style="max-height: 100px">
                                    </div>
                                    @endif
                                </div>
                                @error('image')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div> --}}

                            <div class="form-group mb-0">
                                <label class="form-label w-100">Status Produk</label>
                                <div class="selectgroup selectgroup-pills">
                                    <label class="selectgroup-item">
                                        <input type="radio" name="status" value="1" class="selectgroup-input"
                                            {{ $product->status == 1 ? 'checked' : '' }}>
                                        <span class="selectgroup-button">Aktif</span>
                                    </label>
                                    <label class="selectgroup-item">
                                        <input type="radio" name="status" value="0" class="selectgroup-input"
                                            {{ $product->status == 0 ? 'checked' : '' }}>
                                        <span class="selectgroup-button">Tidak Aktif</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group mt-4">
                                <label class="form-label w-100">Produk Favorit</label>
                                <div class="selectgroup selectgroup-pills">
                                    <label class="selectgroup-item">
                                        <input type="radio" name="is_favorite" value="1" class="selectgroup-input"
                                            {{ $product->is_favorite == 1 ? 'checked' : '' }}>
                                        <span class="selectgroup-button">Ya</span>
                                    </label>
                                    <label class="selectgroup-item">
                                        <input type="radio" name="is_favorite" value="0" class="selectgroup-input"
                                            {{ $product->is_favorite == 0 ? 'checked' : '' }}>
                                        <span class="selectgroup-button">Tidak</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('products.index') }}" class="btn btn-secondary mr-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <!-- JS Libraries -->
    <script src="{{ asset('library/selectric/public/jquery.selectric.min.js') }}"></script>
@endpush
