<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Bán tại quầy') — WebTheThao</title>
    @include('partials.vendor-head', ['icons' => true])
    <link rel="stylesheet" href="{{ asset('css/admin/pos.css') }}">
    <link rel="stylesheet" href="{{ asset('css/shared/bank-qr.css') }}">
    @stack('styles')
</head>
<body class="pos-body">
    @yield('content')
    @include('partials.vendor-scripts')
    <script src="{{ asset('js/admin/pos.js') }}"></script>
    @stack('scripts')
</body>
</html>
