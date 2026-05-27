<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_has_collapse_control_and_no_widget_menu(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard.quality'))
            ->assertOk()
            ->assertSee('Colapsar sidebar')
            ->assertSee('Bandeja')
            ->assertSee('Rendimiento IA')
            ->assertDontSee('Widgets')
            ->assertDontSee('/dashboard/widgets', false);
    }
}
