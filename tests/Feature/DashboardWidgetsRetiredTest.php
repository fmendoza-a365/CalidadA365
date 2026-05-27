<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardWidgetsRetiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_widget_api_routes_are_not_exposed(): void
    {
        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/dashboard/widgets')
            ->assertNotFound();

        $this->actingAs($user)
            ->post('/dashboard/widgets/data', [
                'widget_type' => 'stats_card',
                'config' => ['metric' => 'total_evaluations'],
            ])
            ->assertNotFound();
    }
}
