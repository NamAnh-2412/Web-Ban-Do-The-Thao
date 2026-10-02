<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WebTheThao') — Bán &amp; cho thuê đồ thể thao</title>
    @include('partials.vendor-head')
    <link href="{{ asset('css/storefront/base.css') }}" rel="stylesheet">
    @stack('styles')
    <link href="{{ asset('css/shared/viewport.css') }}" rel="stylesheet">
</head>
<body class="storefront-body">
    <nav class="navbar navbar-expand-lg navbar-dark navbar-wt">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ route('home') }}">
                <span class="brand-mark" aria-hidden="true"></span>
                WebTheThao
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Mở menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('catalog.index') && ! request()->query('offer_mode') ? 'active' : '' }}" href="{{ route('catalog.index') }}">Sản phẩm</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->query('offer_mode') === 'sale' ? 'active' : '' }}" href="{{ route('catalog.index', ['offer_mode' => 'sale']) }}">Mua</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->query('offer_mode') === 'rental' ? 'active' : '' }}" href="{{ route('catalog.index', ['offer_mode' => 'rental']) }}">Thuê</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('policies.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">Chính sách</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{ route('policies.rental') }}">Thuê đồ</a></li>
                            <li><a class="dropdown-item" href="{{ route('policies.returns') }}">Đổi trả</a></li>
                            <li><a class="dropdown-item" href="{{ route('policies.deposit') }}">Cọc thuê</a></li>
                        </ul>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-2">
                    @auth
                        <li class="nav-item"><span class="nav-link disabled">Xin chào, {{ auth()->user()->name }}</span></li>
                        @if (auth()->user()->isStoreAccount())
                            <li class="nav-item"><a class="nav-link" href="{{ route('admin.dashboard') }}">Quản trị</a></li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link nav-cart {{ request()->routeIs('cart.*') ? 'active' : '' }}" href="{{ route('cart.index') }}">
                                    Giỏ <span class="cart-badge">{{ $cartCount ?? 0 }}</span>
                                </a>
                            </li>
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}" href="{{ route('orders.index') }}">Đơn hàng</a></li>
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('account.*') ? 'active' : '' }}" href="{{ route('account.show') }}">Tài khoản</a></li>
                            <li class="nav-item">
                                <a class="nav-link nav-cart {{ request()->routeIs('messages.*') ? 'active' : '' }}" href="{{ route('messages.show') }}">
                                    Tin nhắn
                                    @if (($unreadMessages ?? 0) > 0)
                                        <span class="cart-badge has-items">{{ $unreadMessages }}</span>
                                    @endif
                                </a>
                            </li>
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}">Thông báo</a></li>
                        @endif
                        <li class="nav-item">
                            <form method="post" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-light" type="submit">Đăng xuất</button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link nav-cart {{ request()->routeIs('cart.*') ? 'active' : '' }}" href="{{ route('cart.index') }}">
                                Giỏ <span class="cart-badge">{{ $cartCount ?? 0 }}</span>
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">Đăng nhập</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('register') ? 'active' : '' }}" href="{{ route('register') }}">Đăng ký</a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main class="container py-4 page-enter">
        @if (session('status'))
            <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>{{ session('status') }}</span>
                @if (session('cart_added'))
                    <a class="alert-link fw-semibold" href="{{ route('cart.index') }}">Xem giỏ hàng</a>
                @endif
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="site">
        <div class="container d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
                <div class="footer-brand mb-1">WebTheThao</div>
                <div>Cửa hàng bán &amp; cho thuê dụng cụ thể thao.</div>
            </div>
            <div class="d-flex align-items-end gap-3">
                <a href="{{ route('policies.rental') }}">Thuê</a>
                <a href="{{ route('policies.returns') }}">Đổi trả</a>
                <a href="{{ route('policies.deposit') }}">Cọc</a>
            </div>
        </div>
    </footer>
    @include('partials.vendor-scripts')
    <script src="{{ asset('js/shared/viewport.js') }}"></script>
    <script src="{{ asset('js/storefront/chrome.js') }}"></script>
    @stack('scripts')
</body>
</html>
