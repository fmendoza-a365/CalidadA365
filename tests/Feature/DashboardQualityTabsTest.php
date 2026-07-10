<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardQualityTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_each_quality_dashboard_tab(): void
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $tabs = [
            'calidad' => 'Seguimiento Calidad',
            'mp' => 'Detalle MP',
            'feedback' => 'Seguimiento Feedback',
            'calibracion' => 'Calibración IA vs Monitor',
            'ranking' => 'Ranking de Asesores',
            'gestion' => 'Gestión General',
        ];

        foreach ($tabs as $tab => $expectedText) {
            $this->actingAs($admin)
                ->get(route('dashboard.quality', ['tab' => $tab]))
                ->assertOk()
                ->assertSee($expectedText);
        }
    }
}
