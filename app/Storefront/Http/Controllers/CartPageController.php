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

    public function remove(string $lineId): RedirectResponse
    {
        $this->cart->remove($lineId);

        return redirect()->route('cart.index')->with('status', 'Đã xóa dòng giỏ hàng.');
    }
}
