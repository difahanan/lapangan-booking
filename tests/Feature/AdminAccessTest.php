<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_customer_gets_403(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_open_dashboard(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_new_registration_is_always_customer(): void
    {
        $this->post('/register', [
            'name' => 'Penyerang',
            'email' => 'x@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',          // upaya menyelundupkan role
        ]);

        $this->assertSame('customer', User::where('email', 'x@example.com')->value('role'));
    }
}
