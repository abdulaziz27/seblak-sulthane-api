{{-- Success Alert --}}
@if ($message = Session::get('success'))
    <div class="alert alert-success alert-dismissible show fade">
        <div class="alert-body">
            <button class="close" data-dismiss="alert">
                <span>×</span>
            </button>
            <i class="fas fa-check-circle mr-2"></i> {{ $message }}
        </div>
    </div>
@endif

{{-- Error Alert --}}
@if ($message = Session::get('error'))
    <div class="alert alert-danger alert-dismissible show fade">
        <div class="alert-body">
            <button class="close" data-dismiss="alert">
                <span>×</span>
            </button>
            <i class="fas fa-exclamation-circle mr-2"></i> {{ $message }}
        </div>
    </div>
@endif

{{-- Warning Alert --}}
@if ($message = Session::get('warning'))
    <div class="alert alert-warning alert-dismissible show fade">
        <div class="alert-body">
            <button class="close" data-dismiss="alert">
                <span>×</span>
            </button>
            <i class="fas fa-exclamation-triangle mr-2"></i> {{ $message }}
        </div>
    </div>
@endif

{{-- Info Alert --}}
@if ($message = Session::get('info'))
    <div class="alert alert-info alert-dismissible show fade">
        <div class="alert-body">
            <button class="close" data-dismiss="alert">
                <span>×</span>
            </button>
            <i class="fas fa-info-circle mr-2"></i> {{ $message }}
        </div>
    </div>
@endif

{{-- Validation Errors --}}
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible show fade">
        <div class="alert-body">
            <button class="close" data-dismiss="alert">
                <span>×</span>
            </button>
            <i class="fas fa-exclamation-circle mr-2"></i> Silakan periksa formulir di bawah untuk kesalahan
            <ul class="mt-2 mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
