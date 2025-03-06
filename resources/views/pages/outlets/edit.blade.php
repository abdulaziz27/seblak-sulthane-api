@extends('layouts.app')

@section('title', 'Edit Outlet')

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
                <h1>Edit Outlet</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Forms</a></div>
                    <div class="breadcrumb-item">Outlets</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Edit Outlet: {{ $outlet->name }}</h2>

                <div class="card">
                    <form action="{{ route('outlets.update', $outlet) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="card-header">
                            <h4>Edit Outlet Form</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text"
                                    class="form-control @error('name')
                                is-invalid
                            @enderror"
                                    name="name" value="{{ old('name', $outlet->name) }}">
                                @error('name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Address 1</label>
                                <input type="text"
                                    class="form-control @error('address1')
                                is-invalid
                            @enderror"
                                    name="address1" value="{{ old('address1', $outlet->address1) }}">
                                @error('address1')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Address 2 (Optional)</label>
                                <input type="text"
                                    class="form-control @error('address2')
                                is-invalid
                            @enderror"
                                    name="address2" value="{{ old('address2', $outlet->address2) }}">
                                @error('address2')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Phone (Optional)</label>
                                <input type="text"
                                    class="form-control @error('phone')
                                is-invalid
                            @enderror"
                                    name="phone" value="{{ old('phone', $outlet->phone) }}">
                                @error('phone')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Leader (Optional)</label>
                                <input type="text"
                                    class="form-control @error('leader')
                                is-invalid
                            @enderror"
                                    name="leader" value="{{ old('leader', $outlet->leader) }}">
                                @error('leader')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Notes (Optional)</label>
                                <textarea
                                    class="form-control @error('notes')
                                is-invalid
                            @enderror"
                                    name="notes" rows="3">{{ old('notes', $outlet->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('outlets.index') }}" class="btn btn-secondary mr-2">Cancel</a>
                            <button class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>

            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
