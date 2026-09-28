<?php

namespace App\Http\Support;

use App\Storefront\Support\CartService;
use Illuminate\Support\Str;

class PosCartService extends CartService
{
    public const SESSION = 'admin_pos_desk';

    public function __construct()
    {
        parent::__construct('admin_pos_unused');
    }

    /** @return list<array<string, mixed>> */
    public function lines(): array
    {
        return array_values($this->ticket()['lines'] ?? []);
    }

    public function count(): int
    {
        return collect($this->lines())->sum(fn (array $line) => (int) ($line['quantity'] ?? 1));
    }

    /** @return list<array<string, mixed>> */
    public function tickets(): array
    {
        return array_values($this->state()['tickets']);
    }

    public function activeId(): string
    {
        return $this->state()['active'];
    }

    public function addTicket(): string
    {
        $state = $this->state();
        $n = count($state['tickets']) + 1;
        $id = (string) Str::uuid();
        $state['tickets'][$id] = $this->emptyTicket($id, 'Đơn hàng '.$n);
        $state['active'] = $id;
        $this->put($state);

        return $id;
    }

    public function switchTicket(string $id): void
    {
        $state = $this->state();
        if (! isset($state['tickets'][$id])) {
            return;
        }
        $state['active'] = $id;
        $this->put($state);
    }

    public function removeTicket(string $id): bool
    {
        $state = $this->state();
        if (! isset($state['tickets'][$id]) || count($state['tickets']) < 2) {
            return false;
        }

        unset($state['tickets'][$id]);
        if ($state['active'] === $id) {
            $state['active'] = (string) array_key_first($state['tickets']);
        }
        $this->put($state);

        return true;
    }

    public function add(array $line): void
    {
        $state = $this->state();
        $id = $state['active'];
        $lines = $state['tickets'][$id]['lines'];
        $this->mergeLine($lines, $line);
        $state['tickets'][$id]['lines'] = array_values($lines);
        $this->put($state);
    }

    public function changeQty(string $lineId, int $delta): void
    {
        $state = $this->state();
        $id = $state['active'];
        $lines = $state['tickets'][$id]['lines'];
        foreach ($lines as $i => $line) {
            if ((string) $line['id'] !== $lineId) {
                continue;
            }
            $qty = max(0, (int) $line['quantity'] + $delta);
            if ($qty === 0) {
                unset($lines[$i]);
            } else {
                $lines[$i]['quantity'] = $qty;
            }
            break;
        }
        $state['tickets'][$id]['lines'] = array_values($lines);
        $this->put($state);
    }

    public function remove(string $lineId): void
    {
        $state = $this->state();
        $id = $state['active'];
        $state['tickets'][$id]['lines'] = array_values(array_filter(
            $state['tickets'][$id]['lines'],
            fn (array $line) => (string) ($line['id'] ?? '') !== $lineId,
        ));
        $this->put($state);
    }

    public function rememberCustomer(?int $customerId, string $name, string $phone): void
    {
        $state = $this->state();
        $id = $state['active'];
        $state['tickets'][$id]['customer_id'] = $customerId;
        $state['tickets'][$id]['customer_name'] = $name;
        $state['tickets'][$id]['customer_phone'] = $phone;
        $this->put($state);
    }

    public function customer(): array
    {
        $ticket = $this->ticket();

        return [
            'customer_id' => $ticket['customer_id'] ?? null,
            'customer_name' => $ticket['customer_name'] ?? '',
            'customer_phone' => $ticket['customer_phone'] ?? '',
        ];
    }

    public function clear(): void
    {
        $state = $this->state();
        $id = $state['active'];
        $label = $state['tickets'][$id]['label'] ?? 'Đơn hàng';
        $state['tickets'][$id] = $this->emptyTicket($id, $label);
        $this->put($state);
    }

    /** @return array{active: string, tickets: array<string, array<string, mixed>>} */
    private function state(): array
    {
        $state = session(self::SESSION);
        if (is_array($state) && isset($state['tickets'], $state['active']) && $state['tickets'] !== []) {
            return $state;
        }

        $id = (string) Str::uuid();
        $fresh = [
            'active' => $id,
            'tickets' => [$id => $this->emptyTicket($id, 'Đơn hàng 1')],
        ];
        $this->put($fresh);

        return $fresh;
    }

    /** @return array<string, mixed> */
    private function ticket(): array
    {
        $state = $this->state();

        return $state['tickets'][$state['active']];
    }

    /** @return array<string, mixed> */
    private function emptyTicket(string $id, string $label): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'lines' => [],
            'customer_id' => null,
            'customer_name' => '',
            'customer_phone' => '',
        ];
    }

    /** @param  array{active: string, tickets: array<string, array<string, mixed>>}  $state */
    private function put(array $state): void
    {
        session([self::SESSION => $state]);
    }
}
