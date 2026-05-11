<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_publicly_available(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_public_registration_is_disabled(): void
    {
        $response = $this->post('/register', [
            'username' => 'test.user',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response->assertNotFound();
    }
}
