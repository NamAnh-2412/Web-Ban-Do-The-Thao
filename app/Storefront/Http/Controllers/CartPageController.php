<?php

namespace App\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Storefront\Support\CartPresenter;
use App\Storefront\Support\CartQtyGuard;
use App\Storefront\Support\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartPageController extends Controller
{
    public function __construct(
        private CartService $cart,
        private CartPresenter $presenter,
        private CartQtyGuard $qty,
    ) {}

    public function index(): View
    {
        return view('storefront.cart', $this->presenter->viewData());
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate(CartQtyGuard::addLineRules());

        if ($data['line_type'] === 'sale' && ! $this->qty->saleFits($this->cart->lines(), $data)) {
            return back()
                ->withInput()
                ->withErrors(['quantity' => 'Không còn đủ hàng bán cho số lượng này.']);
        }

        if ($data['line_type'] === 'rental' && ! $this->qty->rentalFits($this->cart->lines(), $data)) {
            return back()
                ->withInput()
                ->withErrors(['quantity' => 'Không còn đủ món thuê trống cho số lượng này trong khoảng ngày đã chọn.']);
        }

        $this->cart->add($data);

        return back(fallback: route('catalog.index'))
            ->with('status', 'Đã thêm vào giỏ hàng.')
            ->with('cart_added', true);
    }

    public function updateQuantity(Request $request, string $lineId): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ], [
            'quantity.required' => 'Nhập số lượng.',
            'quantity.min' => 'Số lượng tối thiểu là 1.',
        ]);

        $line = collect($this->cart->lines())->first(fn (array $row) => (string) ($row['id'] ?? '') === $lineId);
        if ($line === null) {
            return redirect()->route('cart.index')->with('error', 'Dòng giỏ hàng không còn tồn tại.');
        }

        $probe = [
            'line_type' => $line['line_type'],
            'product_variant_id' => $line['product_variant_id'],
            'quantity' => (int) $data['quantity'],
            'rental_start' => $line['rental_start'] ?? null,
            'rental_end' => $line['rental_end'] ?? null,
        ];

        if ($line['line_type'] === 'sale' && ! $this->qty->saleFits($this->cart->lines(), $probe, $lineId)) {
            return back()->withErrors(['quantity' => 'Không còn đủ hàng bán cho số lượng này.']);
        }

        if ($line['line_type'] === 'rental' && ! $this->qty->rentalFits($this->cart->lines(), $probe, $lineId)) {
            return back()->withErrors(['quantity' => 'Không còn đủ món thuê trống cho số lượng này.']);
        }

        $this->cart->updateQuantity($lineId, (int) $data['quantity']);

        return redirect()->route('cart.index')->with('status', 'Đã cập nhật số lượng.');
    }

    public function updateVariant(Request $request, string $lineId): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'min:1'],
        ], [
            'product_variant_id.required' => 'Chọn biến thể sản phẩm.',
        ]);

        $line = collect($this->cart->lines())->first(fn (array $row) => (string) ($row['id'] ?? '') === $lineId);
        if ($line === null) {
            return redirect()->route('cart.index')->with('error', 'Dòng giỏ hàng không còn tồn tại.');
        }

        $probe = [
            'line_type' => $line['line_type'],
            'product_variant_id' => (int) $data['product_variant_id'],
            'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
            'rental_start' => $line['rental_start'] ?? null,
            'rental_end' => $line['rental_end'] ?? null,
        ];

        if ($line['line_type'] === 'sale' && ! $this->qty->saleFits($this->cart->lines(), $probe, $lineId)) {
            return back()->withErrors(['product_variant_id' => 'Biến thể này không còn đủ hàng bán.']);
        }

        if ($line['line_type'] === 'rental' && ! $this->qty->rentalFits($this->cart->lines(), $probe, $lineId)) {
            return back()->withErrors(['product_variant_id' => 'Biến thể này không còn đủ món thuê trống.']);
        }

        $this->cart->updateVariant($lineId, (int) $data['product_variant_id']);

        return redirect()->route('cart.index')->with('status', 'Đã đổi biến thể sản phẩm.');
    }

    public function remove(string $lineId): RedirectResponse
    {
        $this->cart->remove($lineId);

        return redirect()->route('cart.index')->with('status', 'Đã xóa dòng giỏ hàng.');
    }
}
