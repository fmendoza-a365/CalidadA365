<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase, CreatesUsersWithRoles;

    public function test_admin_can_view_users_index(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk();
    }

    public function test_admin_can_create_user(): void
    {
        $admin = $this->userWithRole('admin');
        // Ensure the agent role exists in DB
        $this->userWithRole('agent');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Nuevo',
                'paternal_surname' => 'Usuario',
                'email' => 'nuevo@test.com',
                'username' => 'nuevousuario',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'agent',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@test.com',
            'username' => 'nuevousuario',
        ]);
    }

    public function test_create_user_validates_required_fields(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('users.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'username', 'password', 'role']);
    }

    public function test_create_user_rejects_duplicate_email(): void
    {
        $admin = $this->userWithRole('admin');
        User::factory()->create(['email' => 'existing@test.com']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Dup',
                'email' => 'existing@test.com',
                'username' => 'dupuser',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'agent',
            ])
            ->assertSessionHasErrors(['email']);
    }

    public function test_admin_can_update_user(): void
    {
        $admin = $this->userWithRole('admin');
        $user = $this->userWithRole('agent', ['name' => 'Old Name']);

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'name' => 'New Name',
                'paternal_surname' => 'Updated',
                'email' => $user->email,
                'username' => $user->username,
                'role' => 'agent',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function test_admin_can_delete_user_without_protected_records(): void
    {
        $admin = $this->userWithRole('admin');
        $user = $this->userWithRole('agent');

        $this->actingAs($admin)
            ->delete(route('users.destroy', $user))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->get(route('users.index'))
            ->assertForbidden();
    }
}
