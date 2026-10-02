@php
    $editing = isset($product);
    $variantRows = old('variants');
    if ($variantRows === null) {
        $variantRows = $editing
            ? $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'size' => $variant->size,
                'color' => $variant->color,
                'sale_price' => $variant->sale_price,
                'rental_price_per_day' => $variant->rental_price_per_day,
                'deposit_amount' => $variant->deposit_amount,
                'quantity' => $variant->stock?->quantity_on_hand ?? 0,
                'rental_quantity' => $variant->items_count ?? $variant->items()->count(),
            ])->values()->all()
            : [['sku' => '', 'size' => '', 'color' => '', 'sale_price' => '', 'rental_price_per_day' => '', 'deposit_amount' => '', 'quantity' => 0, 'rental_quantity' => 0]];
    }
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Vui lòng kiểm tra lại dữ liệu:</strong>
        <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="card-title mb-4">1. Thông tin cơ bản</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="name">Tên sản phẩm <span class="text-danger">*</span></label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $product->name ?? '') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="category_id">Danh mục <span class="text-danger">*</span></label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="">-- Chọn danh mục --</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="sport_id">Môn thể thao</label>
                <select class="form-select" id="sport_id" name="sport_id">
                    <option value="">-- Không chọn --</option>
                    @foreach ($sports as $sport)
                        <option value="{{ $sport->id }}" @selected((string) old('sport_id', $product->sport_id ?? '') === (string) $sport->id)>{{ $sport->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="offer_mode">Chế độ <span class="text-danger">*</span></label>
                <select class="form-select" id="offer_mode" name="offer_mode" required>
                    @foreach (['sale' => 'Chỉ bán', 'rental' => 'Chỉ thuê', 'both' => 'Bán và thuê'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('offer_mode', $product->offer_mode->value ?? 'both') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="image">Ảnh đại diện</label>
                <input type="file" class="form-control" id="image" name="image" accept=".jpg,.jpeg,.png,.webp">
                <div class="form-text">JPG, PNG hoặc WebP; tối đa 2 MB. Ảnh mới được lưu trên Cloudinary khi đã cấu hình khóa.</div>
                @if ($editing && $product->imageSrc())
                    <img src="{{ $product->imageSrc() }}" class="img-thumbnail mt-2" style="max-height:120px" alt="">
                @endif
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Mô tả</label>
                <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $product->description ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between mb-4">
            <div>
                <h5 class="card-title mb-1">2. Phân loại sản phẩm</h5>
                <small class="text-muted">SKU bán: tồn bán. SKU thuê: số món thuê. SKU bán và thuê: điền <strong>cả hai</strong> (độc lập).</small>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="add-variant"><i class="fas fa-plus me-1"></i> Thêm phân loại</button>
        </div>
        <div id="variant-list" class="d-grid gap-3" data-next-index="{{ count($variantRows) }}">
            @foreach ($variantRows as $index => $variant)
                <div class="variant-row border rounded-3 p-3">
                    @if (! empty($variant['id']))
                        <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] }}">
                    @endif
                    <div class="row g-3">
                        <div class="col-md-2"><label class="form-label">Mã SKU *</label><input class="form-control" name="variants[{{ $index }}][sku]" value="{{ $variant['sku'] ?? '' }}" required></div>
                        <div class="col-md-1"><label class="form-label">Size</label><input class="form-control" name="variants[{{ $index }}][size]" value="{{ $variant['size'] ?? '' }}"></div>
                        <div class="col-md-2"><label class="form-label">Màu</label><input class="form-control" name="variants[{{ $index }}][color]" value="{{ $variant['color'] ?? '' }}"></div>
                        <div class="col-md-2"><label class="form-label">Giá bán</label><input type="number" min="0" step="1000" class="form-control" name="variants[{{ $index }}][sale_price]" value="{{ $variant['sale_price'] ?? '' }}"></div>
                        <div class="col-md-2"><label class="form-label">Thuê/ngày</label><input type="number" min="0" step="1000" class="form-control" name="variants[{{ $index }}][rental_price_per_day]" value="{{ $variant['rental_price_per_day'] ?? '' }}"></div>
                        <div class="col-md-1"><label class="form-label">Cọc</label><input type="number" min="0" step="1000" class="form-control" name="variants[{{ $index }}][deposit_amount]" value="{{ $variant['deposit_amount'] ?? '' }}"></div>
                        <div class="col-md-1 js-qty-sale"><label class="form-label js-label-sale-qty">Tồn bán *</label><input type="number" min="0" class="form-control" name="variants[{{ $index }}][quantity]" value="{{ $variant['quantity'] ?? 0 }}" required></div>
                        <div class="col-md-1 js-qty-rental"><label class="form-label">Món thuê</label><input type="number" min="0" class="form-control" name="variants[{{ $index }}][rental_quantity]" value="{{ $variant['rental_quantity'] ?? 0 }}"></div>
                        <div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-outline-danger w-100 remove-variant"><i class="fas fa-trash"></i></button></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
<div class="d-grid">
    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-1"></i> {{ $editing ? 'Cập nhật sản phẩm' : 'Lưu sản phẩm' }}</button>
</div>

<template id="variant-template">
    <div class="variant-row border rounded-3 p-3">
        <div class="row g-3">
            <div class="col-md-2"><label class="form-label">Mã SKU *</label><input data-field="sku" class="form-control" required></div>
            <div class="col-md-1"><label class="form-label">Size</label><input data-field="size" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Màu</label><input data-field="color" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Giá bán</label><input type="number" data-field="sale_price" class="form-control" min="0"></div>
            <div class="col-md-2"><label class="form-label">Thuê/ngày</label><input type="number" data-field="rental_price_per_day" class="form-control" min="0"></div>
            <div class="col-md-1"><label class="form-label">Cọc</label><input type="number" data-field="deposit_amount" class="form-control" min="0"></div>
            <div class="col-md-1 js-qty-sale"><label class="form-label js-label-sale-qty">Tồn bán *</label><input type="number" data-field="quantity" class="form-control" min="0" value="0" required></div>
            <div class="col-md-1 js-qty-rental"><label class="form-label">Món thuê</label><input type="number" data-field="rental_quantity" class="form-control" min="0" value="0"></div>
            <div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-outline-danger w-100 remove-variant"><i class="fas fa-trash"></i></button></div>
        </div>
    </div>
</template>
@include('partials.js', ['file' => 'js/admin/product-form.js'])
