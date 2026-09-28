<?php

namespace App\Domain\Payment\Models;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\GatewaySessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewaySession extends Model
{
    protected $fillable = [
        'order_id',
        'gateway',
        'amount',
        'status',
        'gateway_order_id',
        'provider_txn_id',
        'result_code',
        'message',
        'request_payload',
        'response_payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'amount' => 'integer',
            'status' => GatewaySessionStatus::class,
            'result_code' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
