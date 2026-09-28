@extends('layouts.admin')
@section('title', 'Tin nhắn')
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="page-title">Tin nhắn</h1>
            <p class="page-subtitle mb-0">Một hội thoại cửa hàng ↔ từng khách hàng.</p>
        </div>
    </div>
    <div class="admin-card">
        <div class="table-responsive">
            <table class="table admin-table mb-0">
                <thead>
                    <tr>
                        <th>Khách hàng</th>
                        <th>Tin mới nhất</th>
                        <th>Thời gian</th>
                        <th class="text-center">Chưa đọc</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($conversations as $conversation)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $conversation->user->name ?? 'Khách đã xóa' }}</div>
                                <div class="small text-muted">{{ $conversation->user->email ?? '' }}</div>
                            </td>
                            <td class="text-muted">{{ $conversation->last_message ?: 'Chưa có tin nhắn' }}</td>
                            <td class="small text-muted">{{ $conversation->last_message_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-center">
                                @if ($conversation->unread_count)
                                    <span class="badge text-bg-danger">{{ $conversation->unread_count }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.messages.show', $conversation) }}" class="btn btn-sm btn-admin-primary">Mở</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">Chưa có hội thoại nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($conversations->hasPages())
            <div class="p-3">{{ $conversations->links() }}</div>
        @endif
    </div>
@endsection
