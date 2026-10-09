<?php

namespace App\Storefront\Support;

use Illuminate\Support\Str;

class CartService
{
    public const KEY = 'storefront_cart';

    public function __construct(private string $sessionKey = self::KEY) {}

    /** @return list<array<string, mixed>> */
    public function lines(): array
    {
        /** @var list<array<string, mixed>> $lines */
        $lines = session($this->sessionKey, []);

        return array_values($lines);
    }

    public function count(): int
    {
        return collect($this->lines())->sum(fn (array $line) => (int) ($line['quantity'] ?? 1));
    }

    public function add(array $line): void
    {
        $lines = $this->lines();
        $this->mergeLine($lines, $line);
        session([$this->sessionKey => $lines]);
    }

    public function updateQuantity(string $lineId, int $quantity): bool
    {
        $lines = $this->lines();
        foreach ($lines as $i => $line) {
            if ((string) ($line['id'] ?? '') !== $lineId) {
                continue;
            }

            $lines[$i]['quantity'] = max(1, $quantity);
            session([$this->sessionKey => array_values($lines)]);

            return true;
        }

        return false;
    }

    public function updateVariant(string $lineId, int $variantId): bool
    {
        $lines = $this->lines();
        $targetIndex = null;
        $target = null;

        foreach ($lines as $i => $line) {
            if ((string) ($line['id'] ?? '') === $lineId) {
                $targetIndex = $i;
                $target = $line;
                break;
            }
        }

        if ($targetIndex === null || $target === null) {
            return false;
        }

        if ((int) ($target['product_variant_id'] ?? 0) === $variantId) {
            return true;
        }

        $qty = max(1, (int) ($target['quantity'] ?? 1));
        $type = (string) ($target['line_type'] ?? '');
        $start = $target['rental_start'] ?? null;
        $end = $target['rental_end'] ?? null;

        unset($lines[$targetIndex]);
        $lines = array_values($lines);

        foreach ($lines as $i => $existing) {
            if (($existing['line_type'] ?? '') !== $type) {
                continue;
            }
            if ((int) ($existing['product_variant_id'] ?? 0) !== $variantId) {
                continue;
            }
            if ($type === 'rental'
                && (($existing['rental_start'] ?? null) !== $start || ($existing['rental_end'] ?? null) !== $end)
            ) {
                continue;
            }

            $lines[$i]['quantity'] = (int) ($existing['quantity'] ?? 1) + $qty;
            session([$this->sessionKey => array_values($lines)]);

            return true;
        }

        $lines[] = [
            'id' => $lineId,
            'line_type' => $type,
            'product_variant_id' => $variantId,
            'quantity' => $qty,
            'rental_start' => $start,
            'rental_end' => $end,
        ];
        session([$this->sessionKey => array_values($lines)]);

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $line
     */
    protected function mergeLine(array &$lines, array $line): void
    {
        $type = $line['line_type'];
        $variantId = (int) $line['product_variant_id'];
        $qty = max(1, (int) ($line['quantity'] ?? 1));

        if ($type === 'sale') {
            foreach ($lines as $i => $existing) {
                if (($existing['line_type'] ?? '') === 'sale' && (int) $existing['product_variant_id'] === $variantId) {
                    $lines[$i]['quantity'] = (int) $existing['quantity'] + $qty;

                    return;
                }
            }
        }

        if ($type === 'rental') {
            $start = $line['rental_start'] ?? null;
            $end = $line['rental_end'] ?? null;
            foreach ($lines as $i => $existing) {
                if (
                    ($existing['line_type'] ?? '') === 'rental'
                    && (int) $existing['product_variant_id'] === $variantId
                    && ($existing['rental_start'] ?? null) === $start
                    && ($existing['rental_end'] ?? null) === $end
                ) {
                    $lines[$i]['quantity'] = (int) $existing['quantity'] + $qty;

                    return;
                }
            }
        }

        $lines[] = [
            'id' => (string) Str::uuid(),
            'line_type' => $type,
            'product_variant_id' => $variantId,
            'quantity' => $qty,
            'rental_start' => $line['rental_start'] ?? null,
            'rental_end' => $line['rental_end'] ?? null,
        ];
    }

    public function remove(string $lineId): void
    {
        $lines = array_values(array_filter(
            $this->lines(),
            fn (array $line) => (string) ($line['id'] ?? '') !== $lineId,
        ));

        session([$this->sessionKey => $lines]);
    }

    public function clear(): void
    {
        session()->forget($this->sessionKey);
    }
}
