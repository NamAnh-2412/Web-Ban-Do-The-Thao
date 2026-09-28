<?php

namespace Tests\Feature\Auth;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_and_is_logged_in(): void
    {
        $this->post('/dang-ky', [
            'name' => 'Nguyen Van A',
            'email' => 'khach-'.uniqid().'@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '0901111222',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => auth()->user()->email,
            'role' => 'customer',
        ]);
    }

    public function test_customer_can_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post('/dang-nhap', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);

        $this->post('/dang-xuat')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'password123']);

        $this->post('/dang-nhap', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_customer_cannot_open_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_guest_is_redirected_from_checkout_and_admin(): void
    {
        $this->get('/thanh-toan')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_store_account_cannot_use_customer_cart_or_checkout(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/gio-hang')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->get('/thanh-toan')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->get('/don-hang')->assertRedirect(route('admin.dashboard'));
    }

    public function test_store_account_login_goes_to_admin(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password123']);

        $this->post('/dang-nhap', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));
    }
}
