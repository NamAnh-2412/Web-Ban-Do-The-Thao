<?php

namespace Tests\Feature\Auth;

use App\Domain\User\Models\User;
use App\Domain\User\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_request_a_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/quen-mat-khau', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_unknown_email_does_not_reveal_whether_the_account_exists(): void
    {
        Notification::fake();

        $this->post('/quen-mat-khau', ['email' => 'khong-co@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_customer_can_reset_password_and_log_in(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $token = Password::broker()->createToken($user);

        $this->post('/dat-lai-mat-khau', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'matkhaumoi1',
            'password_confirmation' => 'matkhaumoi1',
        ])->assertRedirect(route('login'));

        $this->post('/dang-nhap', [
            'email' => $user->email,
            'password' => 'matkhaumoi1',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }
}
