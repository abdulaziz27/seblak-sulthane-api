@extends('layouts.app')

@section('title', 'Tambah Pengguna')

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
                <h1>Form Pengguna</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item"><a href="#">Formulir</a></div>
                    <div class="breadcrumb-item">Pengguna</div>
                </div>
            </div>

            <div class="section-body">
                <h2 class="section-title">Pengguna</h2>

                <div class="card">
                    <form action="{{ route('users.store') }}" method="POST">
                        @csrf
                        <div class="card-header">
                            <h4>Input Data</h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Nama</label>
                                <input type="text"
                                    class="form-control @error('name')
                                is-invalid
                            @enderror"
                                    name="name">
                                @error('name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email"
                                    class="form-control @error('email')
                                is-invalid
                            @enderror"
                                    name="email">
                                @error('email')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label>Kata Sandi</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </div>
                                    </div>
                                    <input type="password"
                                        class="form-control @error('password')
                                is-invalid
                            @enderror"
                                        name="password">
                                </div>
                                @error('password')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Form group for outlet selection --}}
                            <div class="form-group">
                                <label>Outlet</label>
                                <select name="outlet_id" class="form-control @error('outlet_id') is-invalid @enderror"
                                    {{ Auth::user()->role === 'admin' && (!isset($user) || $user->id !== Auth::id()) ? 'disabled' : '' }}>
                                    <option value="" disabled selected>Pilih Outlet</option>
                                    <option value="null">None (Tidak Ada)</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}"
                                            {{ (isset($user) && $user->outlet_id === $outlet->id) ||
                                            (Auth::user()->role === 'admin' && Auth::user()->outlet_id === $outlet->id)
                                                ? 'selected'
                                                : '' }}>
                                            {{ $outlet->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('outlet_id')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>


                            {{-- Form group for role selection --}}
                            <div class="form-group">
                                <label class="form-label w-100">Peran</label>
                                <div class="selectgroup w-100">
                                    @if (Auth::user()->role === 'owner')
                                        <label class="selectgroup-item">
                                            <input type="radio" name="role" value="owner" class="selectgroup-input"
                                                {{ isset($user) && $user->role === 'owner' ? 'checked' : '' }}>
                                            <span class="selectgroup-button">Owner</span>
                                        </label>
                                        <label class="selectgroup-item">
                                            <input type="radio" name="role" value="admin" class="selectgroup-input"
                                                {{ isset($user) && $user->role === 'admin' ? 'checked' : '' }}>
                                            <span class="selectgroup-button">Admin</span>
                                        </label>
                                    @endif
                                    <label class="selectgroup-item">
                                        <input type="radio" name="role" value="staff" class="selectgroup-input"
                                            {{ (isset($user) && $user->role === 'staff') || Auth::user()->role === 'admin' ? 'checked' : '' }}
                                            {{ Auth::user()->role === 'admin' ? 'disabled' : '' }}>
                                        <span class="selectgroup-button">Staff</span>
                                    </label>
                                </div>
                                @error('role')
                                    <div class="invalid-feedback d-block">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <button class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>

            </div>
        </section>
    </div>
@endsection

@push('scripts')
@endpush
