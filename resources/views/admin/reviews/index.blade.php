@extends('layouts.admin')
@section('title', 'Đánh giá')
@section('content')
    <h1 class="page-title">Đánh giá</h1>
    <p class="page-subtitle mb-4">Khách đánh giá sau khi đơn đã thanh toán.</p>
    <div class="admin-card">
        <table class="table admin-table align-middle">
            <thead><tr><th>Khách</th><th>Sản phẩm</th><th>Loại</th><th>Sao</th><th>Nhận xét</th><th></th></tr></thead>
            <tbody>
            @forelse ($reviews as $review)
                <tr>
                    <td>{{ $review->user->name ?? '—' }}</td>
                    <td>{{ $review->product->name ?? '—' }}</td>
                    <td>{{ $review->kind->value === 'sale' ? 'Mua' : 'Thuê' }}</td>
                    <td>{{ $review->rating }}/5</td>
                    <td>{{ $review->comment }}</td>
                    <td class="text-end">
                        <form method="post" action="{{ route('admin.reviews.destroy', $review) }}" onsubmit="return confirm('Xóa đánh giá này?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Chưa có đánh giá.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $reviews->links() }}</div>
    </div>
@endsection
