<?php

namespace Database\Seeders;

use App\Domain\User\Enums\UserRole;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('seeding.admin.email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Set a valid SEED_ADMIN_EMAIL before running seeders.');
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            if ($existing->role !== UserRole::Admin) {
                throw new RuntimeException('SEED_ADMIN_EMAIL belongs to a non-admin account. Choose a different email.');
            }

            $this->command?->info('Admin already exists; existing account and password kept.');

            return;
        }

        $password = (string) config('seeding.admin.password');
        if (strlen($password) < 12) {
            throw new RuntimeException('Set SEED_ADMIN_PASSWORD to at least 12 characters before creating the admin.');
        }

        $admin = new User;
        $admin->forceFill([
            'name' => config('seeding.admin.name') ?: 'Quản trị WebTheThao',
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->command?->info('Admin created and verified. Sign in with the configured seed credentials.');
    }
}
