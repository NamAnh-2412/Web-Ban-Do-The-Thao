<?php

namespace App\Storefront\Support;

use App\Domain\Inventory\Services\AvailabilityService;

class CartQtyGuard
{
    public function __construct(private AvailabilityService $availability) {}

    /** @return array<string, list<string>> */
    public static function addLineRules(): array
    {
        return [
            'line_type' => ['required', 'in:sale,rental'],
            'product_variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'rental_start' => ['required_if:line_type,rental', 'nullable', 'date'],
            'rental_end' => ['required_if:line_type,rental', 'nullable', 'date', 'after_or_equal:rental_start'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  array<string, mixed>  $data
     */
    public function saleFits(array $existing, array $data): bool
    {
        $wanted = max(1, (int) ($data['quantity'] ?? 1));
        $free = $this->availability->saleAvailability((int) $data['product_variant_id']);
        $already = 0;
        foreach ($existing as $line) {
            if (($line['line_type'] ?? '') !== 'sale') {
                continue;
            }
            if ((int) $line['product_variant_id'] !== (int) $data['product_variant_id']) {
                continue;
            }
            $already += max(1, (int) ($line['quantity'] ?? 1));
        }

        return ($already + $wanted) <= (int) ($free['quantity_available'] ?? 0);
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  array<string, mixed>  $data
     */
    public function rentalFits(array $existing, array $data): bool
    {
        $wanted = max(1, (int) ($data['quantity'] ?? 1));
        $free = $this->availability->rentalAvailability(
            (int) $data['product_variant_id'],
            (string) $data['rental_start'],
            (string) $data['rental_end'],
        );
        $already = 0;
        foreach ($existing as $line) {
            if (($line['line_type'] ?? '') !== 'rental') {
                continue;
            }
            if ((int) $line['product_variant_id'] !== (int) $data['product_variant_id']) {
                continue;
            }
            if (($line['rental_start'] ?? null) !== ($data['rental_start'] ?? null)) {
                continue;
            }
            if (($line['rental_end'] ?? null) !== ($data['rental_end'] ?? null)) {
                continue;
            }
            $already += max(1, (int) ($line['quantity'] ?? 1));
        }

        return ($already + $wanted) <= (int) ($free['quantity_available'] ?? 0);
    }
}
