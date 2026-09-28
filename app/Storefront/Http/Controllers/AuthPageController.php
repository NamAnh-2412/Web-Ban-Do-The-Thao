<?php

namespace App\Storefront\Http\Controllers;

use App\Domain\User\Enums\UserRole;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthPageController extends Controller
{
    public function showLogin(): View
    {
        return view('storefront.auth.login');
    }

    public function showRegister(): View
    {
        return view('storefront.auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không đúng định dạng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])->withInput();
        }

        $request->session()->regenerate();

        if (! Auth::user()->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => 'Tài khoản đã bị khóa.'])->withInput();
        }

        if (Auth::user()->isStoreAccount()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('status', 'Đăng nhập tài khoản cửa hàng. Dùng khu Quản trị để bán hàng, kho và xử lý đơn.');
        }

        return redirect()->intended(route('home'))->with('status', 'Đăng nhập thành công.');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'regex:/^0\d{9}$/'],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.unique' => 'Email này đã được sử dụng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'phone.regex' => 'Số điện thoại phải 10 số, bắt đầu bằng 0.',
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::Customer,
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        event(new Registered($user));

        return redirect()->route('verification.notice')->with('status', 'Tài khoản đã tạo. Hãy xác thực email trước khi đặt hàng.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Đã đăng xuất.');
    }
}
