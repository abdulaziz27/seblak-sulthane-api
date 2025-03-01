<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="#">Seblak Sulthane</a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="#">SS</a>
        </div>
        <ul class="sidebar-menu">



            <li class='nav-item'>
                <a class="nav-link" href="{{ route('home') }}"><i class="fas fa-columns"></i>General Dashboard</a>
            </li>


            <!-- Place this just after the Dashboard menu item -->
            @if (Auth::user()->role === 'owner')
                <li class='nav-item'>
                    <a class="nav-link" href="{{ route('reports.index') }}">
                        <i class="fas fa-file-alt"></i>
                        <span>Reports</span>
                    </a>
                </li>
            @endif


            <li class='nav-item'>
                <a class="nav-link" href="{{ route('users.index') }}"><i class="fas fa-house-user"></i>Users</a>
            </li>




            <li class='nav-item'>
                <a class="nav-link" href="{{ route('products.index') }}"><i class="fas fa-product-hunt"></i>Products</a>
            </li>




            <li class='nav-item'>
                <a class="nav-link" href="{{ route('categories.index') }}"><i class="fas fa-sitemap"></i>Categories</a>
            </li>


            <li class='nav-item'>
                <a class="nav-link" href="{{ route('orders.index') }}">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
            </li>



            <li class='nav-item'>
                <a class="nav-link" href="{{ route('outlets.index') }}"><i class="fas fa-sitemap"></i>Outlets</a>
            </li>


            <li class='nav-item'>
                <a class="nav-link" href="{{ route('members.index') }}"><i class="fas fa-sitemap"></i>Members</a>
            </li>

            <li class="nav-item">
                <a href="{{ route('discounts.index') }}" class="nav-link">
                    <i class="fas fa-percentage"></i>
                    <span>Discounts</span>
                </a>
            </li>

            <li class="menu-header">Inventory</li>
            <li class="nav-item dropdown">
                <a href="#" class="nav-link has-dropdown"><i class="fas fa-boxes"></i><span>Inventory</span></a>
                <ul class="dropdown-menu">
                    <li><a class="nav-link" href="{{ route('raw-materials.index') }}">Bahan Baku</a></li>
                    <li><a class="nav-link" href="{{ route('material-orders.index') }}">Pesan Bahan Baku</a></li>
                </ul>
            </li>

        </ul>

</div>
