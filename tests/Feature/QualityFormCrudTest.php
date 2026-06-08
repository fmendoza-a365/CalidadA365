<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class QualityFormCrudTest extends TestCase
{
    use RefreshDatabase, CreatesUsersWithRoles;

    public function test_admin_can_view_quality_forms_index(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('quality-forms.index'))
            ->assertOk();
    }

    public function test_admin_can_create_quality_form(): void
    {
        $admin = $this->userWithRole('admin');
        $campaign = Campaign::create(['name' => 'Campaign', 'status' => 'active']);

        $this->actingAs($admin)
            ->post(route('quality-forms.store'), [
                'name' => 'Ficha de Calidad',
                'campaign_id' => $campaign->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quality_forms', [
            'name' => 'Ficha de Calidad',
            'campaign_id' => $campaign->id,
        ]);
    }

    public function test_create_quality_form_validates_required_fields(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('quality-forms.store'), [])
            ->assertSessionHasErrors(['name', 'campaign_id']);
    }

    public function test_admin_can_update_quality_form(): void
    {
        $admin = $this->userWithRole('admin');
        $campaign = Campaign::create(['name' => 'Campaign', 'is_active' => true, 'type' => 'inbound']);
        $form = QualityForm::create([
            'campaign_id' => $campaign->id,
            'name' => 'Old Name',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('quality-forms.update', $form), [
                'name' => 'New Name',
                'campaign_id' => $campaign->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('quality_forms', [
            'id' => $form->id,
            'name' => 'New Name',
        ]);
    }

    public function test_admin_can_view_quality_form_detail(): void
    {
        $admin = $this->userWithRole('admin');
        $campaign = Campaign::create(['name' => 'Campaign', 'status' => 'active']);
        $form = QualityForm::create([
            'campaign_id' => $campaign->id,
            'name' => 'Test Form',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('quality-forms.show', $form))
            ->assertOk();
    }

    public function test_manager_can_view_quality_forms(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->get(route('quality-forms.index'))
            ->assertOk();
    }

    public function test_agent_cannot_view_quality_forms(): void
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->get(route('quality-forms.index'))
            ->assertForbidden();
    }
}
