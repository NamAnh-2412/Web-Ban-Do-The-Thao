<?php

namespace App\Domain\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PaymentQrSetting extends Model
{
    protected $fillable = [
        'bank_name',
        'account_name',
        'account_number',
        'qr_path',
        'instructions',
    ];

    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    public function imageUrl(): ?string
    {
        if (! $this->qr_path) {
            return null;
        }

        return asset('storage/'.$this->qr_path);
    }

    public function deleteStoredImage(): void
    {
        if ($this->qr_path) {
            Storage::disk('public')->delete($this->qr_path);
        }
    }
}
