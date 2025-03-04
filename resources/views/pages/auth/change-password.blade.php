@extends('layouts.app')

@section('title', 'Change Password')

@push('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('library/jquery.pwstrength/jquery.pwstrength.min.css') }}">
@endpush

@section('main')
    <div class="main-content">
        <section class="section">
            <div class="section-header">
                <h1>Change Password</h1>
                <div class="section-header-breadcrumb">
                    <div class="breadcrumb-item active"><a href="#">Dashboard</a></div>
                    <div class="breadcrumb-item">Change Password</div>
                </div>
            </div>

            <div class="section-body">
                <div class="row">
                    <div class="col-12 col-md-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Change Your Password</h4>
                            </div>
                            <div class="card-body">
                                @if (session('success'))
                                    <div class="alert alert-success">
                                        {{ session('success') }}
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('password.update.change') }}">
                                    @csrf
                                    @method('PUT')

                                    <div class="form-group">
                                        <label for="current_password">Current Password</label>
                                        <input id="current_password"
                                            type="password"
                                            class="form-control @error('current_password') is-invalid @enderror"
                                            name="current_password"
                                            tabindex="1"
                                            required>
                                        @error('current_password')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="password">New Password</label>
                                        <input id="password"
                                            type="password"
                                            class="form-control pwstrength @error('password') is-invalid @enderror"
                                            data-indicator="pwindicator"
                                            name="password"
                                            tabindex="2"
                                            required>
                                        <div id="pwindicator"
                                            class="pwindicator">
                                            <div class="bar"></div>
                                            <div class="label"></div>
                                        </div>
                                        @error('password')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="password-confirm">Confirm Password</label>
                                        <input id="password-confirm"
                                            type="password"
                                            class="form-control"
                                            name="password_confirmation"
                                            tabindex="3"
                                            required>
                                    </div>

                                    <div class="form-group">
                                        <button type="submit"
                                            class="btn btn-primary"
                                            tabindex="4">
                                            Change Password
                                        </button>
                                        <a href="{{ route('home') }}" class="btn btn-secondary ml-2">Cancel</a>
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
@endpush
