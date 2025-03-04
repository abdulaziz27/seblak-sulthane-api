<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="#">Seblak Sulthane</a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="#">SS</a>
        </div>
        <ul class="sidebar-menu">
            <!-- Dashboard Section -->
            <li class="menu-header">Dashboard</li>
            <li class='nav-item'>
                <a class="nav-link" href="{{ route('home') }}">
                    <i class="fas fa-columns"></i><span>Dashboard</span>
                </a>
            </li>

            @if (Auth::user()->role === 'owner')
            <li class='nav-item'>
                <a class="nav-link" href="{{ route('reports.index') }}">
                    <i class="fas fa-chart-bar"></i><span>Reports</span>
                </a>
            </li>
            @endif

            <!-- Master Data Section -->
            <li class="menu-header">Master Data</li>
            <li class="nav-item dropdown">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-database"></i><span>Master Data</span>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="nav-link" href="{{ route('products.index') }}">
                        <i class="fas fa-utensils"></i>Products
                    </a></li>
                    <li><a class="nav-link" href="{{ route('categories.index') }}">
                        <i class="fas fa-tags"></i>Categories
                    </a></li>
                    <li><a class="nav-link" href="{{ route('outlets.index') }}">
                        <i class="fas fa-store"></i>Outlets
                    </a></li>
                    <li><a class="nav-link" href="{{ route('members.index') }}">
                        <i class="fas fa-users"></i>Members
                    </a></li>
                    <li><a class="nav-link" href="{{ route('discounts.index') }}">
                        <i class="fas fa-percentage"></i>Discounts
                    </a></li>
                </ul>
            </li>

            <!-- User Management Section -->
            @if (Auth::user()->role === 'owner' || Auth::user()->role === 'admin')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('users.index') }}">
                    <i class="fas fa-user-cog"></i><span>User Management</span>
                </a>
            </li>
            @endif

            <!-- Order Section -->
            <li class="menu-header">Operations</li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('orders.index') }}">
                    <i class="fas fa-shopping-cart"></i><span>Orders</span>
                </a>
            </li>

            <!-- Inventory Section -->
            <li class="menu-header">Inventory</li>
            <li class="nav-item dropdown">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-boxes"></i><span>Inventory</span>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="nav-link" href="{{ route('raw-materials.index') }}">
                        <i class="fas fa-cube"></i>Raw Materials
                    </a></li>
                    <li><a class="nav-link" href="{{ route('material-orders.index') }}">
                        <i class="fas fa-clipboard-list"></i>Material Orders
                    </a></li>
                </ul>
            </li>

            <!-- Account Settings Section -->
            <li class="menu-header">Account</li>
            <li class="nav-item dropdown">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-user-circle"></i><span>My Account</span>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="nav-link" href="{{ route('profile') }}">
                        <i class="fas fa-user-edit"></i>My Profile
                    </a></li>
                    <li><a class="nav-link" href="{{ route('password.change') }}">
                        <i class="fas fa-key"></i>Change Password
                    </a></li>
                </ul>
            </li>

            <!-- Standalone Logout Button -->
            <li class='nav-item'>
                <a class="nav-link text-danger" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit()">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>
            </li>
        </ul>
    </aside>
</div>
