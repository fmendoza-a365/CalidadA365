<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Interaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class SupervisorModuleAccessTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    public function test_supervisor_does_not_see_restricted_modules_in_navigation(): void
    {
        $supervisor = $this->userWithRole('supervisor');

        $this->actingAs($supervisor)
            ->get(route('dashboard.quality'))
            ->assertOk()
            ->assertDontSee('Transcripciones')
            ->assertDontSee('Insights IA')
            ->assertDontSee('Bandeja')
            ->assertDontSee('Muestreo QA');
    }

    public function test_supervisor_cannot_access_restricted_modules_by_url(): void
    {
        $supervisor = $this->userWithRole('supervisor');

        $this->actingAs($supervisor)->get(route('transcripts.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('transcripts.create'))->assertForbidden();
        $this->actingAs($supervisor)->post(route('transcripts.store'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('insights.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('work-queue.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('sampling.index'))->assertForbidden();
    }

    public function test_supervisor_cannot_edit_or_delete_transcripts_by_url(): void
    {
        $supervisor = $this->userWithRole('supervisor');
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $campaign = Campaign::create(['name' => 'Campaña']);

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'transcripts/test.txt',
            'file_name' => 'test.txt',
            'source_type' => 'text',
            'transcript_text' => 'Texto de prueba',
            'status' => 'uploaded',
        ]);

        $this->actingAs($supervisor)->get(route('transcripts.edit', $interaction))->assertForbidden();
        $this->actingAs($supervisor)->put(route('transcripts.update', $interaction))->assertForbidden();
        $this->actingAs($supervisor)->delete(route('transcripts.destroy', $interaction))->assertForbidden();
    }
}
