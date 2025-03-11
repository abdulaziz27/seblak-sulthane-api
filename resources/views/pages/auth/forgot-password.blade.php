@extends('layouts.auth')

@section('title', 'Lupa Kata Sandi')

@push('style')
    <!-- CSS Libraries -->
@endpush

@section('main')
    <div class="card card-primary">
        <div class="card-header">
            <h4>Lupa Kata Sandi</h4>
        </div>

        <div class="card-body">
            <p class="text-muted">Kami akan mengirimkan tautan untuk mengatur ulang kata sandi Anda</p>

            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email"
                        type="email"
                        class="form-control @error('email') is-invalid @enderror"
                        name="email"
                        value="{{ old('email') }}"
                        tabindex="1"
                        required
                        autofocus>
                    @error('email')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="form-group">
                    <button type="submit"
                        class="btn btn-primary btn-lg btn-block"
                        tabindex="4">
                        Kirim Tautan Reset Kata Sandi
                    </button>
                </div>
            </form>

            <div class="mt-5 text-center">
                <a href="{{ route('login') }}">Kembali ke login</a>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- JS Libraies -->
    <!-- Page Specific JS File -->
@endpush
