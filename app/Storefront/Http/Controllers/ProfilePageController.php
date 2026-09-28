<?php

namespace App\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfilePageController extends Controller
{
    public function show(Request $request): View
    {
        return view('storefront.account.profile', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^0\d{9}$/'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'phone.regex' => 'Số điện thoại phải 10 số, bắt đầu bằng 0.',
            'password.min' => 'Mật khẩu mới ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        $user->name = $data['name'];
        $user->phone = $data['phone'] ?? null;

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return back()->with('status', 'Đã lưu hồ sơ.');
    }
}
