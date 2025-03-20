@extends('layouts.app')

@section('title', 'Ubah Kata Sandi')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/jquery.pwstrength/jquery.pwstrength.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Ubah Kata Sandi</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dasbor</a></div>
                    <div class="breadcrumb-item">Ubah Kata Sandi</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        @include('layouts.alert')
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Ubah Kata Sandi Anda</h4>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('password.update.change') }}">
                                    @csrf
                                    @method('PUT')

                                    <div class="form-group">
                                        <label for="current_password">Kata Sandi Saat Ini</label>
                                        <div class="input-group">
                                            <input id="current_password"
                                                type="password"
                                                class="form-control @error('current_password') is-invalid @enderror"
                                                name="current_password"
                                                tabindex="1"
                                                required>
                                            <div class="input-group-append">
                                                <div class="input-group-text toggle-password" data-target="current_password">
                                                    <i class="fas fa-eye"></i>
                                                </div>
                                            </div>
                                        </div>
                                        @error('current_password')
                                            <div class="invalid-feedback d-block">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="password">Kata Sandi Baru</label>
                                        <div class="input-group">
                                            <input id="password"
                                                type="password"
                                                class="form-control pwstrength @error('password') is-invalid @enderror"
                                                data-indicator="pwindicator"
                                                name="password"
                                                tabindex="2"
                                                required>
                                            <div class="input-group-append">
                                                <div class="input-group-text toggle-password" data-target="password">
                                                    <i class="fas fa-eye"></i>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="pwindicator"
                                            class="pwindicator">
                                            <div class="bar"></div>
                                            <div class="label"></div>
                                        </div>
                                        @error('password')
                                            <div class="invalid-feedback d-block">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="password-confirm">Konfirmasi Kata Sandi</label>
                                        <div class="input-group">
                                            <input id="password-confirm"
                                                type="password"
                                                class="form-control"
                                                name="password_confirmation"
                                                tabindex="3"
                                                required>
                                            <div class="input-group-append">
                                                <div class="input-group-text toggle-password" data-target="password-confirm">
                                                    <i class="fas fa-eye"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <button type="submit"
                                            class="btn btn-primary"
                                            tabindex="4">
                                            Ubah Kata Sandi
                                        </button>
                                        <a href="{{ route('home') }}" class="btn btn-secondary ml-2">Batal</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <!-- JS Libraies -->
    <script src="{{ asset('library/jquery.pwstrength/jquery.pwstrength.min.js') }}"></script>

    <!-- Page Specific JS File -->
    <script src="{{ asset('js/page/auth-register.js') }}"></script>

    <!-- Script untuk toggle password visibility -->
    <script>
        $(document).ready(function() {
            $('.toggle-password').click(function() {
                const target = $(this).data('target');
                const input = $('#' + target);
                const icon = $(this).find('i');

                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
        });
    </script>
@endpush
