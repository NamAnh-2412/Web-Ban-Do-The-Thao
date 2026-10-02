<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Quản trị') — WebTheThao</title>
    @include('partials.vendor-head', ['icons' => true])
    <link rel="stylesheet" href="{{ asset('css/admin/base.css') }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/shared/viewport.css') }}">
</head>
<body class="admin-body">
    <aside class="admin-sidebar" id="admin-sidebar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <i class="fas fa-cube"></i>
            <span>WebTheThao</span>
        </a>
        <nav class="admin-nav">
            <div class="admin-nav-label">Quản lý chung</div>
            <a class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                <i class="fas fa-chart-pie"></i><span>Tổng quan</span>
            </a>
            <a class="admin-nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">
                <i class="fas fa-layer-group"></i><span>Danh mục</span>
            </a>
            <a class="admin-nav-link {{ request()->routeIs('admin.sports.*') ? 'active' : '' }}" href="{{ route('admin.sports.index') }}">
                <i class="fas fa-futbol"></i><span>Môn thể thao</span>
            </a>
            <a class="admin-nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">
                <i class="fas fa-box-open"></i><span>Sản phẩm</span>
            </a>
            <a class="admin-nav-link {{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}" href="{{ route('admin.inventory.index') }}">
                <i class="fas fa-warehouse"></i><span>Tồn kho / món thuê</span>
            </a>
            <div class="admin-nav-label mt-3">Bán hàng</div>
            <a class="admin-nav-link {{ request()->routeIs('admin.pos.*') ? 'active' : '' }}" href="{{ route('admin.pos.index') }}">
                <i class="fas fa-cash-register"></i><span>Bán tại quầy</span>
            </a>
            <a class="admin-nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}">
                <i class="fas fa-receipt"></i><span>Đơn hàng</span>
            </a>
            <a class="admin-nav-link {{ request()->routeIs('admin.messages.*') ? 'active' : '' }}" href="{{ route('admin.messages.index') }}">
                <i class="fas fa-comments"></i><span>Tin nhắn</span>
                @if (($unreadMessages ?? 0) > 0)
                    <span class="admin-nav-badge">{{ $unreadMessages }}</span>
                @endif
            </a>
            <a class="admin-nav-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}">
                <i class="fas fa-money-bill"></i><span>Thanh toán</span>
            </a>
            <a class="admin-nav-link {{ request()->routeIs('admin.finance.*') ? 'active' : '' }}" href="{{ route('admin.finance.index') }}">
                <i class="fas fa-file-invoice-dollar"></i><span>Tài chính</span>
            </a>
            <a class="admin-nav-link {{ request()->routeIs('admin.rentals.*') ? 'active' : '' }}" href="{{ route('admin.rentals.index') }}">
                <i class="fas fa-calendar-check"></i><span>Lịch thuê</span>
            </a>
            @if (auth()->user()->isOwnerAdmin())
                <a class="admin-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                    <i class="fas fa-users"></i><span>Người dùng</span>
                </a>
            @endif
            <div class="admin-nav-label mt-3">Khuyến mãi &amp; báo cáo</div>
            @if (auth()->user()->isOwnerAdmin())
                <a class="admin-nav-link {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}" href="{{ route('admin.coupons.index') }}">
                    <i class="fas fa-ticket"></i><span>Mã giảm giá</span>
                </a>
            @endif
            <a class="admin-nav-link {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}" href="{{ route('admin.reviews.index') }}">
                <i class="fas fa-star"></i><span>Đánh giá</span>
            </a>
            @if (auth()->user()->isOwnerAdmin())
                <a class="admin-nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}">
                    <i class="fas fa-chart-line"></i><span>Báo cáo</span>
                </a>
            @endif
        </nav>
        <div class="admin-user">
            <div class="small fw-semibold text-truncate">{{ auth()->user()->name }}</div>
            <div class="small text-muted text-truncate mb-2">{{ auth()->user()->email }}</div>
            <div class="d-flex gap-2">
                <a href="{{ route('home') }}" class="btn btn-sm btn-outline-secondary flex-grow-1" title="Xem cửa hàng">
                    <i class="fas fa-store"></i>
                </a>
                <form action="{{ route('logout') }}" method="POST" class="flex-grow-1">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100" title="Đăng xuất">
                        <i class="fas fa-right-from-bracket"></i>
                    </button>
                </form>
            </div>
        </div>
    </aside>
    <div class="admin-nav-backdrop" data-admin-nav-backdrop></div>
    <div class="admin-main">
        <div class="admin-topbar">
            <button class="btn btn-sm btn-outline-secondary admin-menu-btn" type="button" data-admin-nav-toggle aria-controls="admin-sidebar" aria-expanded="false">
                <i class="fas fa-bars" aria-hidden="true"></i>
                <span>Menu</span>
            </button>
            <span class="fw-semibold">WebTheThao</span>
        </div>
        <main class="admin-content">
            @if (session('status') || session('success'))
                <div class="alert alert-success">{{ session('status') ?: session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-warning">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
    @include('partials.vendor-scripts')
    <script src="{{ asset('js/shared/viewport.js') }}"></script>
    @stack('scripts')
</body>
</html>
