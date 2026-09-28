<?php

namespace App\Http\Controllers\Admin;

use App\Domain\User\Enums\UserRole;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->isOwnerAdmin(), 403);

        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isOwnerAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([UserRole::Staff->value, UserRole::Admin->value])],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.unique' => 'Email này đã được sử dụng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'role.required' => 'Chọn vai trò cửa hàng.',
            'role.in' => 'Chỉ tạo nhân viên hoặc quản trị cửa hàng.',
        ]);

        User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::from($data['role']),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Đã tạo tài khoản cửa hàng.');
    }

    public function toggle(User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->isOwnerAdmin(), 403);

        if ($user->is(auth()->user())) {
            return back()->with('error', 'Không khóa tài khoản đang đăng nhập.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('success', $user->is_active ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.');
    }
}
