<?php

namespace Tests\Feature\Deploy;

use App\Domain\User\Enums\UserRole;
use App\Domain\User\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AdminSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_verified_admin_and_keeps_the_password(): void
    {
        config([
            'seeding.admin.name' => 'Owner',
            'seeding.admin.email' => 'owner@example.com',
            'seeding.admin.password' => 'correct-horse-battery',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(password_verify('correct-horse-battery', $admin->password));

        $hash = $admin->password;
        config(['seeding.admin.password' => 'a-different-password-value']);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame($hash, $admin->fresh()->password);
    }

    public function test_seed_rejects_a_short_password(): void
    {
        config([
            'seeding.admin.email' => 'owner@example.com',
            'seeding.admin.password' => 'short-pass',
        ]);

        $this->expectException(RuntimeException::class);
        $this->seed(AdminUserSeeder::class);
    }

    public function test_seed_rejects_an_email_that_belongs_to_a_customer(): void
    {
        User::factory()->create(['email' => 'customer@example.com']);
        config([
            'seeding.admin.email' => 'customer@example.com',
            'seeding.admin.password' => 'correct-horse-battery',
        ]);

        $this->expectException(RuntimeException::class);
        $this->seed(AdminUserSeeder::class);
    }

    public function test_production_seed_requires_an_admin_email(): void
    {
        $this->app['env'] = 'production';
        config(['seeding.admin.email' => null]);

        $this->expectException(RuntimeException::class);
        $this->app->make(DatabaseSeeder::class)->run();
    }

    public function test_local_seed_keeps_the_demo_accounts_when_admin_email_is_blank(): void
    {
        config(['seeding.admin.email' => null]);

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::query()->where('email', 'admin@gmail.com')->where('role', UserRole::Admin)->exists());
        $this->assertTrue(User::query()->where('email', 'khach@webthethao.test')->exists());
    }
}
