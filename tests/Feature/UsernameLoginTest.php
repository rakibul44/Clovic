<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_sign_in_with_username_and_customers_keep_email_login(): void
    {
        $password = Str::random(24);
        $owner = User::factory()->create(['password' => $password]);
        $owner->forceFill(['username' => 'test-owner', 'role' => 'owner'])->save();
        $this->get('/login')->assertOk()->assertSee('Email or username');
        $this->post('/login', ['email' => 'test-owner', 'password' => $password])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($owner);
        $this->post('/logout');
        $customer = User::factory()->create(['password' => $password]);
        $this->post('/login', ['email' => $customer->email, 'password' => $password])->assertRedirect('/account');
        $this->assertAuthenticatedAs($customer);
    }

    public function test_inactive_username_and_wrong_password_are_rejected(): void
    {
        $password = Str::random(24);
        $owner = User::factory()->create(['password' => $password]);
        $owner->forceFill(['username' => 'test-owner', 'role' => 'owner', 'active' => false])->save();
        $this->post('/login', ['email' => 'test-owner', 'password' => $password])->assertSessionHasErrors('email');
        $this->assertGuest();
        $owner->update(['active' => true]);
        $this->post('/login', ['email' => 'test-owner', 'password' => Str::random(24)])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
