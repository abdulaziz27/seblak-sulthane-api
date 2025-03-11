@extends('layouts.app')

@section('title', 'Profil Saya')

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
                <h1>Profil Saya</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dasbor</a></div>
                    <div class="breadcrumb-item">Profil Saya</div>
                </div>
            </div>

            <div class="section-body">
                @include('layouts.alert')

                <div class="row mt-sm-4">
                    <!-- Kartu Profil -->
                    <div class="col-12 col-md-12 col-lg-4">
                        <div class="card profile-widget">
                            <div class="profile-widget-header">
                                <img alt="image" src="{{ asset('img/avatar/avatar-1.png') }}" class="rounded-circle profile-widget-picture">
                                <div class="profile-widget-items">
                                    <div class="profile-widget-item">
                                        <div class="profile-widget-item-label">Peran</div>
                                        <div class="profile-widget-item-value text-capitalize">{{ $user->role }}</div>
                                    </div>
                                    <div class="profile-widget-item">
                                        <div class="profile-widget-item-label">Outlet</div>
                                        <div class="profile-widget-item-value">{{ $user->outlet ? $user->outlet->name : 'Belum Ditugaskan' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="profile-widget-description pb-0">
                                <div class="profile-widget-name">{{ $user->name }} <div class="text-muted d-inline font-weight-normal"><div class="slash"></div> {{ $user->email }}</div></div>
                                <p>Akun dibuat pada {{ $user->created_at->format('d M Y') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Edit Profil -->
                    <div class="col-12 col-md-12 col-lg-8">
                        <div class="card">
                            <form method="POST" action="{{ route('profile.update') }}" class="needs-validation" novalidate="">
                                @csrf
                                @method('PUT')
                                <div class="card-header">
                                    <h4>Edit Profil</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="form-group col-md-12 col-12">
                                            <label>Nama Lengkap</label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $user->name) }}" required>
                                            @error('name')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="form-group col-md-12 col-12">
                                            <label>Email</label>
                                            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', $user->email) }}" required>
                                            @error('email')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="form-group col-md-12 col-12">
                                            <label>Kata Sandi Saat Ini <small class="text-muted">(Hanya isi jika ingin mengubah kata sandi)</small></label>
                                            <input type="password" class="form-control @error('current_password') is-invalid @enderror" name="current_password">
                                            @error('current_password')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="form-group col-md-6 col-12">
                                            <label>Kata Sandi Baru</label>
                                            <input type="password" class="form-control @error('new_password') is-invalid @enderror" name="new_password">
                                            @error('new_password')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                        <div class="form-group col-md-6 col-12">
                                            <label>Konfirmasi Kata Sandi Baru</label>
                                            <input type="password" class="form-control @error('new_password_confirmation') is-invalid @enderror" name="new_password_confirmation">
                                            @error('new_password_confirmation')
                                                <div class="invalid-feedback">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                    </div>

                                    <!-- Informasi hanya baca -->
                                    <div class="row">
                                        <div class="form-group col-md-6 col-12">
                                            <label>Peran</label>
                                            <input type="text" class="form-control" value="{{ ucfirst($user->role) }}" disabled>
                                        </div>
                                        <div class="form-group col-md-6 col-12">
                                            <label>Outlet</label>
                                            <input type="text" class="form-control" value="{{ $user->outlet ? $user->outlet->name : 'Belum Ditugaskan' }}" disabled>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-right">
                                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
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
@endpush
