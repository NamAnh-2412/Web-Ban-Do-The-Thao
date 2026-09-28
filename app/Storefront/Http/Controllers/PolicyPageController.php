<?php

namespace App\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PolicyPageController extends Controller
{
    public function rental(): View
    {
        return view('storefront.policies.rental');
    }

    public function returns(): View
    {
        return view('storefront.policies.returns');
    }

    public function deposit(): View
    {
        return view('storefront.policies.deposit');
    }
}
