<?php

namespace Tests\Feature\Storefront;

use App\Domain\User\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_sends_verification_and_blocks_checkout(): void
    {
        Notification::fake();

        $this->post('/dang-ky', [
            'name' => 'Khach Verify',
            'email' => 'verify-'.uniqid().'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0901111222',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'like', 'verify-%')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->actingAs($user)
            ->get('/thanh-toan')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_signed_link_verifies_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('home'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_customer_can_update_profile_without_changing_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Cu',
            'phone' => '0901111222',
            'email' => 'keep@example.com',
        ]);

        $this->actingAs($user)
            ->put('/tai-khoan', [
                'name' => 'Nguyen Van B',
                'phone' => '0912345678',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('Nguyen Van B', $user->name);
        $this->assertSame('0912345678', $user->phone);
        $this->assertSame('keep@example.com', $user->email);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpass123', $user->password));
    }
}
