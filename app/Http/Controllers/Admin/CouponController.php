<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Coupon\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        return view('admin.coupons.index', [
            'coupons' => Coupon::query()->withCount('redemptions')->orderByDesc('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.coupons.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Coupon::query()->create($this->validated($request));

        return redirect()->route('admin.coupons.index')->with('success', 'Đã thêm mã giảm giá.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.edit', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request, $coupon));

        return redirect()->route('admin.coupons.index')->with('success', 'Đã cập nhật mã giảm giá.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', 'Đã xóa mã giảm giá.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon)],
            'name' => ['required', 'string', 'max:255'],
            'discount_type' => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'applies_to' => ['required', 'in:sale,rental,both'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'Vui lòng nhập mã.',
            'code.unique' => 'Mã này đã tồn tại.',
            'name.required' => 'Vui lòng nhập tên mã.',
            'discount_value.required' => 'Vui lòng nhập giá trị giảm.',
        ]);

        $data['code'] = mb_strtoupper(trim($data['code']));
        $data['min_order_amount'] = $data['min_order_amount'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        if ($data['discount_type'] === 'percent' && (float) $data['discount_value'] > 100) {
            $data['discount_value'] = 100;
        }

        return $data;
    }
}
