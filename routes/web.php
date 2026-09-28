<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MessageController as AdminMessageController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\RentalController as AdminRentalController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\SportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Storefront\Http\Controllers\AuthPageController;
use App\Storefront\Http\Controllers\AvailabilityPageController;
use App\Storefront\Http\Controllers\CartPageController;
use App\Storefront\Http\Controllers\CatalogPageController;
use App\Storefront\Http\Controllers\CheckoutPageController;
use App\Storefront\Http\Controllers\EmailVerificationController;
use App\Storefront\Http\Controllers\MessagePageController;
use App\Storefront\Http\Controllers\MomoCallbackController;
use App\Storefront\Http\Controllers\NotificationPageController;
use App\Storefront\Http\Controllers\PolicyPageController;
use App\Storefront\Http\Controllers\ProfilePageController;
use App\Storefront\Http\Controllers\ShippingLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogPageController::class, 'home'])->name('home');
Route::get('/san-pham', [CatalogPageController::class, 'index'])->name('catalog.index');
Route::get('/san-pham/{slug}', [CatalogPageController::class, 'show'])->name('catalog.show');

Route::prefix('chinh-sach')->name('policies.')->group(function () {
    Route::get('/thue', [PolicyPageController::class, 'rental'])->name('rental');
    Route::get('/doi-tra', [PolicyPageController::class, 'returns'])->name('returns');
    Route::get('/coc', [PolicyPageController::class, 'deposit'])->name('deposit');
});

Route::get('/kho/ban', [AvailabilityPageController::class, 'sale'])->name('availability.sale');
Route::get('/kho/thue', [AvailabilityPageController::class, 'rental'])->name('availability.rental');
Route::get('/thue/bao-gia', [AvailabilityPageController::class, 'quote'])->name('rental.quote');

Route::get('/giao-hang/tinh', [ShippingLookupController::class, 'provinces'])->name('shipping.provinces');
Route::get('/giao-hang/quan', [ShippingLookupController::class, 'districts'])->name('shipping.districts');
Route::get('/giao-hang/phuong', [ShippingLookupController::class, 'wards'])->name('shipping.wards');
Route::get('/giao-hang/phi', [ShippingLookupController::class, 'fee'])->name('shipping.fee');

Route::get('/thanh-toan/momo/return', [MomoCallbackController::class, 'returned'])->name('payments.momo.return');
Route::post('/thanh-toan/momo/ipn', [MomoCallbackController::class, 'ipn'])->name('payments.momo.ipn');

Route::middleware('customer')->group(function () {
    Route::get('/gio-hang', [CartPageController::class, 'index'])->name('cart.index');
    Route::post('/gio-hang', [CartPageController::class, 'add'])->name('cart.add');
    Route::delete('/gio-hang/{lineId}', [CartPageController::class, 'remove'])->name('cart.remove');
});

Route::middleware('guest')->group(function () {
    Route::get('/dang-nhap', [AuthPageController::class, 'showLogin'])->name('login');
    Route::post('/dang-nhap', [AuthPageController::class, 'login'])->name('login.store');
    Route::get('/dang-ky', [AuthPageController::class, 'showRegister'])->name('register');
    Route::post('/dang-ky', [AuthPageController::class, 'register'])->name('register.store');
});

Route::post('/dang-xuat', [AuthPageController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware(['auth', 'customer'])->group(function () {
    Route::get('/tai-khoan', [ProfilePageController::class, 'show'])->name('account.show');
    Route::put('/tai-khoan', [ProfilePageController::class, 'update'])->name('account.update');
    Route::get('/tin-nhan', [MessagePageController::class, 'show'])->name('messages.show');
    Route::post('/tin-nhan', [MessagePageController::class, 'store'])->name('messages.store');
    Route::get('/don-hang', [CheckoutPageController::class, 'orders'])->name('orders.index');
    Route::get('/don-hang/{order}', [CheckoutPageController::class, 'orderShow'])->name('orders.show');
    Route::post('/don-hang/{order}/gia-han', [CheckoutPageController::class, 'requestExtension'])->name('orders.extensions.store');
    Route::get('/thong-bao', [NotificationPageController::class, 'index'])->name('notifications.index');
});

Route::middleware(['auth', 'customer', 'verified'])->group(function () {
    Route::get('/thanh-toan', [CheckoutPageController::class, 'show'])->name('checkout.show');
    Route::post('/thanh-toan', [CheckoutPageController::class, 'store'])->name('checkout.store');
    Route::post('/don-hang/{order}/momo', [MomoCallbackController::class, 'start'])->name('orders.momo');
    Route::post('/don-hang/{order}/danh-gia', [CheckoutPageController::class, 'review'])->name('orders.review');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('sports', SportController::class)->except(['show']);
    Route::resource('products', AdminProductController::class)->except(['show']);

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/stocks', [InventoryController::class, 'upsertStock'])->name('inventory.stocks');
    Route::post('/inventory/items', [InventoryController::class, 'storeItem'])->name('inventory.items');
    Route::patch('/inventory/items/{item}/status', [InventoryController::class, 'changeItemStatus'])->name('inventory.items.status');

    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
    Route::post('/pos/tickets', [PosController::class, 'addTicket'])->name('pos.tickets.store');
    Route::post('/pos/tickets/{ticketId}', [PosController::class, 'switchTicket'])->name('pos.tickets.switch');
    Route::delete('/pos/tickets/{ticketId}', [PosController::class, 'removeTicket'])->name('pos.tickets.destroy');
    Route::post('/pos/lines', [PosController::class, 'add'])->name('pos.add');
    Route::post('/pos/lines/{lineId}/qty', [PosController::class, 'changeQty'])->name('pos.qty');
    Route::delete('/pos/lines/{lineId}', [PosController::class, 'remove'])->name('pos.remove');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/confirm', [AdminOrderController::class, 'confirm'])->name('orders.confirm');
    Route::post('/orders/{order}/mark-paid', [AdminOrderController::class, 'markPaid'])->name('orders.mark-paid');
    Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/complete', [AdminOrderController::class, 'complete'])->name('orders.complete');
    Route::post('/orders/{order}/fulfillment', [AdminOrderController::class, 'fulfillment'])->name('orders.fulfillment');

    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/qr', [AdminPaymentController::class, 'saveQr'])->name('payments.qr');
    Route::post('/payments/{payment}/confirm', [AdminPaymentController::class, 'confirm'])->name('payments.confirm');
    Route::post('/payments/refund', [AdminPaymentController::class, 'refund'])->name('payments.refund');

    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/transactions', [FinanceController::class, 'transactions'])->name('finance.transactions');
    Route::get('/finance/export', [FinanceController::class, 'export'])->name('finance.export');
    Route::patch('/finance/{order}/status', [FinanceController::class, 'updateStatus'])->name('finance.update-status');

    Route::get('/rentals', [AdminRentalController::class, 'index'])->name('rentals.index');
    Route::get('/rentals/{booking}', [AdminRentalController::class, 'show'])->name('rentals.show');
    Route::post('/rentals/{booking}/confirm', [AdminRentalController::class, 'confirm'])->name('rentals.confirm');
    Route::post('/rentals/{booking}/activate', [AdminRentalController::class, 'activate'])->name('rentals.activate');
    Route::post('/rentals/{booking}/return', [AdminRentalController::class, 'returnBooking'])->name('rentals.return');
    Route::post('/rentals/{booking}/deposit-refund', [AdminRentalController::class, 'refundDeposit'])->name('rentals.deposit-refund');
    Route::post('/rentals/{booking}/extensions', [AdminRentalController::class, 'requestExtension'])->name('rentals.extensions.store');
    Route::post('/rentals/{booking}/extensions/{extension}/collect', [AdminRentalController::class, 'collectAndApproveExtension'])->name('rentals.extensions.collect');
    Route::post('/rentals/{booking}/extensions/{extension}/approve', [AdminRentalController::class, 'approveExtension'])->name('rentals.extensions.approve');
    Route::post('/rentals/{booking}/extensions/{extension}/reject', [AdminRentalController::class, 'rejectExtension'])->name('rentals.extensions.reject');

    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/messages', [AdminMessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/users/{user}', [AdminMessageController::class, 'start'])->name('messages.start');
    Route::get('/messages/{conversation}', [AdminMessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [AdminMessageController::class, 'store'])->name('messages.store');

    Route::middleware('owner')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/toggle', [AdminUserController::class, 'toggle'])->name('users.toggle');

        Route::resource('coupons', CouponController::class)->except(['show']);
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });
});
