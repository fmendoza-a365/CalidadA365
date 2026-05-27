<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_create_prompts_for_password_without_printing_it(): void
    {
        $password = 'Secure123!';

        $this->artisan('admin:create', [
            'email' => 'admin@example.com',
            'name' => 'Admin User',
        ])
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutput('Admin user created successfully.')
            ->doesntExpectOutput("Password: {$password}")
            ->assertExitCode(0);

        $user = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('admin', $user->username);
        $this->assertSame('Admin User', $user->name);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_admin_create_rejects_mismatched_password_confirmation(): void
    {
        $this->artisan('admin:create', [
            'email' => 'admin@example.com',
            'name' => 'Admin User',
        ])
            ->expectsQuestion('Password', 'Secure123!')
            ->expectsQuestion('Confirm password', 'Different123!')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@example.com',
        ]);
    }
}
