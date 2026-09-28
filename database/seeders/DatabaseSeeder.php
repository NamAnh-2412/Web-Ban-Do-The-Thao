<?php

namespace Database\Seeders;

use App\Domain\Coupon\Enums\CouponAppliesTo;
use App\Domain\Coupon\Enums\DiscountType;
use App\Domain\Coupon\Models\Coupon;
use App\Domain\User\Enums\UserRole;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedAccounts();

            Coupon::query()->firstOrCreate(
                ['code' => 'SALE10'],
                [
                    'name' => 'Giảm 10% tiền hàng/thuê',
                    'discount_type' => DiscountType::Percent,
                    'discount_value' => 10,
                    'min_order_amount' => 0,
                    'applies_to' => CouponAppliesTo::Both,
                    'max_uses' => 100,
                    'is_active' => true,
                ],
            );

            $this->call([
                ProductCatalogSeeder::class,
                InventorySeeder::class,
            ]);
        });
    }

    private function seedAccounts(): void
    {
        if (filled(config('seeding.admin.email'))) {
            $this->call(AdminUserSeeder::class);

            return;
        }

        if (! app()->environment('local', 'testing')) {
            $this->call(AdminUserSeeder::class);

            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Quản trị WebTheThao',
                'password' => '193850091011',
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'nhanvien@webthethao.test'],
            [
                'name' => 'Nhân viên cửa hàng',
                'password' => 'password',
                'role' => UserRole::Staff,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'khach@webthethao.test'],
            [
                'name' => 'Khách demo',
                'password' => 'password',
                'role' => UserRole::Customer,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
