@if ($product)
    <form method="post" action="{{ route('admin.inventory.suspension', $product) }}">
        @csrf
        @if ($product->is_suspended)
            <span class="badge text-bg-secondary me-1">Đang tạm khóa</span>
            <button class="btn btn-sm btn-outline-success">Mở lại</button>
        @else
            <button class="btn btn-sm btn-outline-warning">Tạm khóa</button>
        @endif
    </form>
@endif
