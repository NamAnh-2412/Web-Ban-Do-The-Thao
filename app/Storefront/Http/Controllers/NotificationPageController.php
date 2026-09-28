<?php

namespace App\Storefront\Http\Controllers;

use App\Domain\Notification\Services\NotificationWriter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationPageController extends Controller
{
    public function __construct(private NotificationWriter $notifications) {}

    public function index(Request $request): View
    {
        return view('storefront.notifications.index', [
            'notifications' => $this->notifications->listByUser((int) $request->user()->id),
        ]);
    }
}
