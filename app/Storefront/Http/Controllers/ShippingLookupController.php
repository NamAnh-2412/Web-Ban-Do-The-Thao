<?php

namespace App\Storefront\Http\Controllers;

use App\Gateway\Services\ShippingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShippingLookupController extends Controller
{
    public function __construct(private ShippingService $shipping) {}

    public function provinces(): JsonResponse
    {
        return response()->json(['data' => $this->shipping->provinces()]);
    }

    public function districts(Request $request): JsonResponse
    {
        $id = (int) $request->query('province_id');

        return response()->json(['data' => $id > 0 ? $this->shipping->districts($id) : []]);
    }

    public function wards(Request $request): JsonResponse
    {
        $id = (int) $request->query('district_id');

        return response()->json(['data' => $id > 0 ? $this->shipping->wards($id) : []]);
    }

    public function fee(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to_district_id' => ['required', 'integer', 'min:1'],
            'to_ward_code' => ['required', 'string'],
            'item_count' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $total = $this->shipping->quoteFee(
                (int) $data['to_district_id'],
                (string) $data['to_ward_code'],
                (int) ($data['item_count'] ?? 1),
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();

            return response()->json([
                'message' => $errors['to_ward_code'][0] ?? $errors['to_district_id'][0] ?? 'Không tính được phí.',
            ], 422);
        }

        return response()->json(['data' => ['total' => $total]]);
    }
}
