<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\InsightReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InsightsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_only_sees_insight_reports_for_managed_campaigns(): void
    {
        $manager = $this->userWithRoleAndPermissions('manager', ['view_insights']);
        $creator = User::factory()->create();
        $managedCampaign = Campaign::create(['name' => 'Managed Campaign']);
        $otherCampaign = Campaign::create(['name' => 'Other Campaign']);

        $manager->managedCampaigns()->attach($managedCampaign);

        $this->report($managedCampaign, $creator);
        $this->report($otherCampaign, $creator);

        $response = $this->actingAs($manager)->get(route('insights.index'));

        $response->assertOk();
        $response->assertSee('Managed Campaign');
        $response->assertDontSee('Other Campaign');
    }

    public function test_manager_cannot_view_insight_report_for_unmanaged_campaign(): void
    {
        $manager = $this->userWithRoleAndPermissions('manager', ['view_insights']);
        $creator = User::factory()->create();
        $otherCampaign = Campaign::create(['name' => 'Other Campaign']);
        $report = $this->report($otherCampaign, $creator);

        $response = $this->actingAs($manager)->get(route('insights.show', $report));

        $response->assertForbidden();
    }

    public function test_manager_cannot_generate_insights_for_unmanaged_campaign(): void
    {
        $manager = $this->userWithRoleAndPermissions('manager', ['view_insights', 'generate_insights']);
        $otherCampaign = Campaign::create(['name' => 'Other Campaign']);

        $response = $this->actingAs($manager)->post(route('insights.generate'), [
            'campaign_id' => $otherCampaign->id,
            'type' => 'operational',
            'days' => 30,
        ]);

        $response->assertForbidden();
    }

    private function userWithRoleAndPermissions(string $roleName, array $permissionNames): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function report(Campaign $campaign, User $creator): InsightReport
    {
        return InsightReport::create([
            'campaign_id' => $campaign->id,
            'type' => 'operational',
            'date_range_start' => now()->subDays(30)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'summary_content' => 'Summary',
            'key_findings' => ['executive_summary' => 'Summary'],
            'generated_by' => $creator->id,
        ]);
    }
}
