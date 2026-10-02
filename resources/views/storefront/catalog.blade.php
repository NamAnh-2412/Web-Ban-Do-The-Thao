@extends('storefront.layouts.app')
@include('partials.css', ['file' => 'css/storefront/catalog.css'])

@php
    $heading = match ($filters['offer_mode'] ?? '') {
        'sale' => 'Mua',
        'rental' => 'Thuê',
        default => 'Sản phẩm',
    };
@endphp

@section('title', $heading)

@section('content')
    <h1 class="h3 mb-3">{{ $heading }}</h1>
    <form class="filter-bar row g-2 mb-4" method="get" action="{{ route('catalog.index') }}">
        <div class="col-md-3">
            <input class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm tên sản phẩm">
        </div>
        <div class="col-md-2">
            <select class="form-select" name="sport_id">
                <option value="">Môn</option>
                @foreach ($sports as $sport)
                    <option value="{{ $sport->id }}" @selected((string) ($filters['sport_id'] ?? '') === (string) $sport->id)>{{ $sport->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="category_id">
                <option value="">Danh mục</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="offer_mode">
                <option value="">Mua &amp; thuê</option>
                <option value="sale" @selected(($filters['offer_mode'] ?? '') === 'sale')>Mua</option>
                <option value="rental" @selected(($filters['offer_mode'] ?? '') === 'rental')>Thuê</option>
            </select>
        </div>
        <div class="col-md-2">
            <input class="form-control" name="size" value="{{ $filters['size'] ?? '' }}" placeholder="Size">
        </div>
        <div class="col-md-1">
            <button class="btn btn-success w-100" type="submit">Lọc</button>
        </div>
    </form>

    <div class="vp-grid">
        @forelse ($products as $index => $product)
            <div class="reveal" style="--delay: {{ min($index, 11) * 55 }}ms">
                @include('storefront.partials.product-card', ['offerFocus' => $filters['offer_mode'] ?? null])
            </div>
        @empty
            <div><div class="alert alert-light border">Không có sản phẩm khớp bộ lọc.</div></div>
        @endforelse
    </div>

    <div class="mt-4">{{ $products->withQueryString()->links('pagination::bootstrap-5') }}</div>
@endsection
