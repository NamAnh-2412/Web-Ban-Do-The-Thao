@extends('layouts.admin')
@section('title', 'Người dùng')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title">Người dùng</h1>
            <p class="page-subtitle mb-0">Khách tự đăng ký. Tài khoản cửa hàng do quản trị cấp tại đây.</p>
        </div>
        @if (auth()->user()->isOwnerAdmin())
            <a href="{{ route('admin.users.create') }}" class="btn btn-admin-primary"><i class="fas fa-plus me-1"></i> Thêm tài khoản cửa hàng</a>
        @endif
    </div>
    <div class="admin-card">
        <table class="table admin-table">
            <thead><tr><th>Tên</th><th>Email</th><th>Vai trò</th><th>TT</th><th></th></tr></thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->role->label() }}</td>
                    <td>{{ $user->is_active ? 'active' : 'locked' }}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            @if ($user->isCustomer())
                                <form method="post" action="{{ route('admin.messages.start', $user) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Nhắn tin</button>
                                </form>
                            @endif
                            @if ($user->is(auth()->user()))
                                <span class="text-muted small">Đang đăng nhập</span>
                            @else
                                <form method="post" action="{{ route('admin.users.toggle', $user) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $user->is_active ? 'Khóa' : 'Mở' }}</button></form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Chưa có người dùng.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
@endsection
