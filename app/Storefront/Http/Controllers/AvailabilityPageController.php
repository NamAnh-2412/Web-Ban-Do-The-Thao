<?php

namespace App\Storefront\Http\Controllers;

use App\Domain\Inventory\Services\AvailabilityService;
use App\Domain\Rental\Services\RentalPricer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityPageController extends Controller
{
    public function __construct(
        private AvailabilityService $availability,
        private RentalPricer $pricer,
    ) {}

    public function sale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json($this->availability->saleAvailability((int) $data['product_variant_id']));
    }

    public function rental(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        return response()->json($this->availability->rentalAvailability(
            (int) $data['product_variant_id'],
            $data['start_date'],
            $data['end_date'],
        ));
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'weekly_rate' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($this->pricer->quote(
            $data['start_date'],
            $data['end_date'],
            (float) $data['daily_rate'],
            $data['weekly_rate'] ?? null,
            (float) ($data['deposit_amount'] ?? 0),
        ));
    }
}
