<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\User;
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
            ->assertDontSee('Campañas')
            ->assertDontSee('Fichas de Calidad')
            ->assertDontSee('Transcripciones')
            ->assertDontSee('Insights IA')
            ->assertDontSee('Bandeja')
            ->assertDontSee('Muestreo QA')
            ->assertDontSee('Calibración IA')
            ->assertDontSee('Dashboard Gestión')
            ->assertDontSee('Exportar calibración')
            ->assertDontSee('Monitores')
            ->assertDontSee('Resumen Monitor');
    }

    public function test_supervisor_cannot_access_restricted_modules_by_url(): void
    {
        $supervisor = $this->userWithRole('supervisor');

        $this->actingAs($supervisor)->get(route('campaigns.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('quality-forms.index'))->assertForbidden();
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

    public function test_supervisor_dashboard_only_includes_own_agents_data(): void
    {
        $admin = $this->userWithRole('admin');
        $supervisor = $this->userWithRole('supervisor');
        $otherSupervisor = $this->userWithRole('supervisor');
        $agent = $this->userWithRole('agent', ['name' => 'Agente Equipo Julio']);
        $otherAgent = $this->userWithRole('agent', ['name' => 'Agente Externo Julio']);
        $campaign = Campaign::create(['name' => 'Campaña Compartida', 'type' => 'inbound', 'is_active' => true]);
        $version = $this->qualityFormVersion($campaign, $admin);

        CampaignUserAssignment::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        CampaignUserAssignment::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $otherAgent->id,
            'supervisor_id' => $otherSupervisor->id,
            'is_active' => true,
        ]);

        $this->evaluationFor($campaign, $version, $agent, $supervisor, $admin, 96);
        $this->evaluationFor($campaign, $version, $otherAgent, $otherSupervisor, $admin, 55);

        $this->actingAs($supervisor)
            ->get(route('dashboard.quality'))
            ->assertOk()
            ->assertSee('Agente Equipo Julio')
            ->assertDontSee('Agente Externo Julio');
    }

    private function qualityFormVersion(Campaign $campaign, User $admin): QualityFormVersion
    {
        $form = QualityForm::create([
            'campaign_id' => $campaign->id,
            'name' => 'Ficha de prueba',
            'created_by' => $admin->id,
        ]);

        return QualityFormVersion::create([
            'quality_form_id' => $form->id,
            'version_number' => 1,
            'status' => 'published',
            'is_active' => true,
            'published_at' => now(),
            'published_by' => $admin->id,
        ]);
    }

    private function evaluationFor(
        Campaign $campaign,
        QualityFormVersion $version,
        User $agent,
        User $supervisor,
        User $admin,
        int $score
    ): Evaluation {
        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'transcripts/'.$agent->id.'.txt',
            'file_name' => $agent->id.'.txt',
            'source_type' => 'text',
            'transcript_text' => 'Texto de prueba',
            'status' => 'uploaded',
        ]);

        return Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'type' => 'manual',
            'evaluator_id' => $admin->id,
            'total_score' => $score,
            'max_possible_score' => 100,
            'percentage_score' => $score,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'visible_to_agent_at' => now(),
        ]);
    }
}
