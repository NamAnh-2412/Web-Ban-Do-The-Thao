<?php

namespace App\Providers;

use App\Domain\Chat\Models\Conversation;
use App\Domain\Chat\Services\ChatService;
use App\Domain\Coupon\Services\CouponService;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Payment\Models\Payment;
use App\Domain\Rental\Models\RentalBooking;
use App\Domain\User\Models\User;
use App\Gateway\Services\CheckoutOrchestrator;
use App\Http\Controllers\Admin\PosController;
use App\Storefront\Support\CartPresenter;
use App\Storefront\Support\CartService;
use App\Http\Support\PosCartService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->when(PosController::class)
            ->needs(CartPresenter::class)
            ->give(function ($app) {
                return new CartPresenter(
                    $app->make(PosCartService::class),
                    $app->make(CheckoutOrchestrator::class),
                    $app->make(CouponService::class),
                );
            });
    }

    public function boot(): void
    {
        Route::model('item', InventoryItem::class);
        Route::model('booking', RentalBooking::class);
        Route::model('payment', Payment::class);
        Route::model('conversation', Conversation::class);

        View::composer('storefront.layouts.app', function ($view) {
            $user = auth()->user();
            $unread = 0;
            if ($user instanceof User && $user->isCustomer()) {
                $unread = app(ChatService::class)->unreadCountForCustomer($user);
            }

            $view->with('cartCount', app(CartService::class)->count());
            $view->with('unreadMessages', $unread);
        });

        View::composer('layouts.admin', function ($view) {
            $user = auth()->user();
            $unread = 0;
            if ($user instanceof User && $user->isStoreAccount()) {
                $unread = app(ChatService::class)->unreadConversationCountForStore();
            }

            $view->with('unreadMessages', $unread);
        });
    }
}
