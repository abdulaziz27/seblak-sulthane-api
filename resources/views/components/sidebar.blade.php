<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="#">Seblak Sulthane</a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="#">SS</a>
        </div>
        <ul class="sidebar-menu">
            <!-- Bagian Dashboard -->
            <li class="menu-header">Dasbor</li>
            <li class='nav-item'>
                <a class="nav-link" href="{{ route('home') }}">
                    <i class="fas fa-columns"></i><span>Dasbor</span>
                </a>
            </li>

            @if (Auth::user()->role === 'owner')
            <li class='nav-item'>
                <a class="nav-link" href="{{ route('reports.index') }}">
                    <i class="fas fa-chart-bar"></i><span>Laporan</span>
                </a>
            </li>
            @endif

            <!-- Bagian Master Data -->
            <li class="menu-header">Master Data</li>
            <li class="nav-item dropdown">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-database"></i><span>Master Data</span>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="nav-link" href="{{ route('products.index') }}">
                        <i class="fas fa-utensils"></i>Produk
                    </a></li>
                    <li><a class="nav-link" href="{{ route('categories.index') }}">
                        <i class="fas fa-tags"></i>Kategori
                    </a></li>
                    <li><a class="nav-link" href="{{ route('outlets.index') }}">
                        <i class="fas fa-store"></i>Outlet
                    </a></li>
                    <li><a class="nav-link" href="{{ route('members.index') }}">
                        <i class="fas fa-users"></i>Member
                    </a></li>
                    <li><a class="nav-link" href="{{ route('discounts.index') }}">
                        <i class="fas fa-percentage"></i>Diskon
                    </a></li>
                </ul>
            </li>

            <!-- Bagian Manajemen Pengguna -->
            @if (Auth::user()->role === 'owner' || Auth::user()->role === 'admin')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('users.index') }}">
                    <i class="fas fa-user-cog"></i><span>Manajemen Pengguna</span>
                </a>
            </li>
            @endif

            <!-- Bagian Pesanan -->
            <li class="menu-header">Operasional</li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('orders.index') }}">
                    <i class="fas fa-shopping-cart"></i><span>Pesanan POS</span>
                </a>
            </li>

            <!-- Bagian Inventaris -->
            <li class="menu-header">Inventaris</li>
            <li class="nav-item dropdown">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-boxes"></i><span>Inventaris</span>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="nav-link" href="{{ route('raw-materials.index') }}">
                        <i class="fas fa-cube"></i>Bahan Baku
                    </a></li>
                    <li><a class="nav-link" href="{{ route('material-orders.index') }}">
                        <i class="fas fa-clipboard-list"></i>Pemesanan Bahan
                    </a></li>
                </ul>
            </li>

            <!-- Bagian Akun -->
            <li class="menu-header">Akun</li>
            <li class="nav-item dropdown">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-user-circle"></i><span>Akun Saya</span>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="nav-link" href="{{ route('profile') }}">
                        <i class="fas fa-user-edit"></i>Profil Saya
                    </a></li>
                    <li><a class="nav-link" href="{{ route('password.change') }}">
                        <i class="fas fa-key"></i>Ubah Kata Sandi
                    </a></li>
                </ul>
            </li>

            <!-- Tombol Logout -->
            <li class='nav-item'>
                <a class="nav-link text-danger" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit()">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Keluar</span>
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>
            </li>
        </ul>
    </aside>
</div>
