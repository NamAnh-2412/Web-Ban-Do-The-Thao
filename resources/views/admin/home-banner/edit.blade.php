@extends('layouts.admin')
@section('title', 'Banner trang chủ')
@section('content')
    <h1 class="page-title">Banner trang chủ</h1>
    <p class="page-subtitle mb-4">Chọn ảnh hiển thị ở đầu trang chủ. Nút Mua ngay và Thuê đồ vẫn nằm trên banner.</p>

    <div class="admin-card p-4" style="max-width:720px">
        <h2 class="h5 mb-3">Ảnh hiện tại</h2>
        <img src="{{ $previewUrl }}" alt="Banner trang chủ" class="img-fluid border rounded mb-2" style="max-height:260px;width:100%;object-fit:cover">
        <p class="small text-muted mb-4">
            @if ($usingDefault)
                Đang dùng ảnh mặc định. Tải ảnh mới để thay.
            @else
                Đang dùng ảnh bạn đã tải lên.
            @endif
        </p>

        <form method="post" action="{{ route('admin.home-banner.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label" for="image">Chọn ảnh mới</label>
                <input
                    class="form-control @error('image') is-invalid @enderror"
                    id="image"
                    name="image"
                    type="file"
                    accept="image/*"
                    required
                >
                @error('image')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Nên dùng ảnh ngang, khoảng 1600×550 px, tối đa 5MB.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">Lưu ảnh banner</button>
                @unless ($usingDefault)
                    <button
                        class="btn btn-outline-secondary"
                        type="submit"
                        form="home-banner-reset"
                        onclick="return confirm('Trở lại ảnh banner mặc định?')"
                    >Dùng ảnh mặc định</button>
                @endunless
            </div>
        </form>

        @unless ($usingDefault)
            <form id="home-banner-reset" method="post" action="{{ route('admin.home-banner.destroy') }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endunless
    </div>
@endsection
