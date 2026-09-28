<?php

namespace App\Domain\User\Models;

use App\Domain\Chat\Models\Conversation;
use App\Domain\Order\Models\Order;
use App\Domain\User\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function isStoreAccount(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Staff], true);
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    /** Nhân viên / quản trị cửa hàng (khu /admin). */
    public function isAdmin(): bool
    {
        return $this->isStoreAccount();
    }

    /** Quản trị gốc — được cấp thêm tài khoản cửa hàng. */
    public function isOwnerAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }
}
