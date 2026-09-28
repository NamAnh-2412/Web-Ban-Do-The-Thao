@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="mb-3">
    <label class="form-label" for="code">Mã <span class="text-danger">*</span></label>
    <input class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $coupon->code ?? '') }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="name">Tên hiển thị <span class="text-danger">*</span></label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $coupon->name ?? '') }}" required>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="discount_type">Kiểu giảm <span class="text-danger">*</span></label>
        <select class="form-select" id="discount_type" name="discount_type" required>
            <option value="percent" @selected(old('discount_type', $coupon->discount_type->value ?? 'percent') === 'percent')>Phần trăm (%)</option>
            <option value="fixed" @selected(old('discount_type', $coupon->discount_type->value ?? '') === 'fixed')>Số tiền cố định</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="discount_value">Giá trị <span class="text-danger">*</span></label>
        <input class="form-control" type="number" step="0.01" min="0.01" id="discount_value" name="discount_value" value="{{ old('discount_value', $coupon->discount_value ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="min_order_amount">Đơn tối thiểu</label>
        <input class="form-control" type="number" step="0.01" min="0" id="min_order_amount" name="min_order_amount" value="{{ old('min_order_amount', $coupon->min_order_amount ?? 0) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="applies_to">Áp dụng</label>
        <select class="form-select" id="applies_to" name="applies_to" required>
            @foreach (['both' => 'Bán và thuê', 'sale' => 'Chỉ tiền hàng', 'rental' => 'Chỉ tiền thuê'] as $value => $label)
                <option value="{{ $value }}" @selected(old('applies_to', $coupon->applies_to->value ?? 'both') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="max_uses">Số lượt tối đa</label>
        <input class="form-control" type="number" min="1" id="max_uses" name="max_uses" value="{{ old('max_uses', $coupon->max_uses ?? '') }}" placeholder="Để trống = không giới hạn">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="starts_at">Bắt đầu</label>
        <input class="form-control" type="date" id="starts_at" name="starts_at" value="{{ old('starts_at', isset($coupon) && $coupon->starts_at ? $coupon->starts_at->toDateString() : '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="ends_at">Hết hạn</label>
        <input class="form-control" type="date" id="ends_at" name="ends_at" value="{{ old('ends_at', isset($coupon) && $coupon->ends_at ? $coupon->ends_at->toDateString() : '') }}">
    </div>
</div>
<div class="form-check mt-3 mb-4">
    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $coupon->is_active ?? true))>
    <label class="form-check-label" for="is_active">Đang hoạt động</label>
</div>
<button class="btn btn-success"><i class="fas fa-save"></i> Lưu</button>
<a href="{{ route('admin.coupons.index') }}" class="btn btn-secondary">Hủy</a>
