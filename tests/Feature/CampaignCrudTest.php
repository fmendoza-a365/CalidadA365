<?php

namespace Tests\Feature;

use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class CampaignCrudTest extends TestCase
{
    use RefreshDatabase, CreatesUsersWithRoles;

    public function test_admin_can_view_campaigns_index(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('campaigns.index'))
            ->assertOk();
    }

    public function test_admin_can_create_campaign(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('campaigns.store'), [
                'name' => 'Nueva Campaña',
                'description' => 'Descripción de prueba',
                'type' => 'inbound',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Nueva Campaña',
            'type' => 'inbound',
        ]);
    }

    public function test_create_campaign_validates_required_fields(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('campaigns.store'), [])
            ->assertSessionHasErrors(['name', 'type']);
    }

    public function test_admin_can_update_campaign(): void
    {
        $admin = $this->userWithRole('admin');
        $campaign = Campaign::create(['name' => 'Old Name', 'type' => 'inbound', 'is_active' => true]);

        $this->actingAs($admin)
            ->put(route('campaigns.update', $campaign), [
                'name' => 'New Name',
                'type' => 'inbound',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'name' => 'New Name',
        ]);
    }

    public function test_admin_can_delete_campaign(): void
    {
        $admin = $this->userWithRole('admin');
        $campaign = Campaign::create(['name' => 'To Delete', 'type' => 'inbound', 'is_active' => true]);

        // Admin needs delete_campaigns permission (synced via userWithRole)
        $this->actingAs($admin)
            ->delete(route('campaigns.destroy', $campaign))
            ->assertRedirect();

        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    }

    public function test_manager_can_view_but_not_create_campaigns(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->get(route('campaigns.index'))
            ->assertOk();

        $this->actingAs($manager)
            ->get(route('campaigns.create'))
            ->assertForbidden();
    }

    public function test_campaign_supports_parent_child_hierarchy(): void
    {
        $admin = $this->userWithRole('admin');
        $parent = Campaign::create(['name' => 'Parent', 'type' => 'inbound', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('campaigns.store'), [
                'name' => 'Subcampaña',
                'parent_id' => $parent->id,
                'type' => 'inbound',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Subcampaña',
            'parent_id' => $parent->id,
        ]);
    }
}
