@php
    $action = $action ?? route('admin.finance.index');
@endphp
<div class="admin-card p-3 mb-4">
    <form method="GET" action="{{ $action }}" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1" for="finance-search">Tìm kiếm</label>
            <input id="finance-search" type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Mã đơn, tên, SĐT">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1" for="finance-gateway">Phương thức</label>
            <select id="finance-gateway" name="gateway" class="form-select">
                <option value="">Tất cả</option>
                @foreach ($methods as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['gateway'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1" for="finance-status">Trạng thái TT</label>
            <select id="finance-status" name="payment_status" class="form-select">
                <option value="">Tất cả</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1" for="finance-min">Số tiền từ</label>
            <input id="finance-min" type="number" min="0" step="1000" name="min_amount" value="{{ $filters['min_amount'] ?? '' }}" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1" for="finance-max">Số tiền đến</label>
            <input id="finance-max" type="number" min="0" step="1000" name="max_amount" value="{{ $filters['max_amount'] ?? '' }}" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1" for="finance-from">Từ ngày</label>
            <input id="finance-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1" for="finance-to">Đến ngày</label>
            <input id="finance-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
        </div>
        @if (! empty($showSort))
            <div class="col-md-2">
                <label class="form-label small mb-1" for="finance-sort">Sắp xếp</label>
                <select id="finance-sort" name="sort" class="form-select">
                    <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Mới nhất</option>
                    <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Cũ nhất</option>
                    <option value="amount_desc" @selected(($filters['sort'] ?? '') === 'amount_desc')>Tổng tiền giảm</option>
                    <option value="amount_asc" @selected(($filters['sort'] ?? '') === 'amount_asc')>Tổng tiền tăng</option>
                </select>
            </div>
        @endif
        <div class="col-auto">
            <button class="btn btn-admin-primary">Lọc</button>
            <a href="{{ $action }}" class="btn btn-outline-secondary">Xóa lọc</a>
        </div>
    </form>
    @error('date_to') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
    @error('max_amount') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
</div>
