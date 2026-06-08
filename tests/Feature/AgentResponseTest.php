<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class AgentResponseTest extends TestCase
{
    use RefreshDatabase, CreatesUsersWithRoles;

    private function publishedEvaluation(): array
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $supervisor = $this->userWithRole('supervisor');
        $campaign = Campaign::create(['name' => 'Campaign', 'is_active' => true, 'type' => 'inbound']);
        $form = QualityForm::create([
            'campaign_id' => $campaign->id,
            'name' => 'Quality Form',
            'created_by' => $admin->id,
        ]);
        $version = QualityFormVersion::create([
            'quality_form_id' => $form->id,
            'version_number' => 1,
            'status' => 'published',
        ]);
        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'transcripts/test.txt',
            'file_name' => 'test.txt',
            'source_type' => 'text',
            'transcript_text' => 'Agente: Hola. Cliente: Gracias.',
            'status' => 'uploaded',
        ]);
        $evaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'type' => 'ai',
            'total_score' => 80,
            'max_possible_score' => 100,
            'percentage_score' => 80,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'visible_to_agent_at' => now(),
        ]);

        return [$agent, $evaluation];
    }

    public function test_agent_can_accept_published_evaluation(): void
    {
        [$agent, $evaluation] = $this->publishedEvaluation();

        $this->actingAs($agent)
            ->post(route('evaluations.respond', $evaluation), [
                'response_type' => 'accept',
                'commitment_comment' => 'Entendido, voy a mejorar.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('agent_responses', [
            'evaluation_id' => $evaluation->id,
            'agent_id' => $agent->id,
            'response_type' => 'accept',
        ]);
    }

    public function test_agent_can_dispute_published_evaluation(): void
    {
        [$agent, $evaluation] = $this->publishedEvaluation();

        $this->actingAs($agent)
            ->post(route('evaluations.respond', $evaluation), [
                'response_type' => 'dispute',
                'dispute_reason' => 'No estoy de acuerdo con la evaluación.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('agent_responses', [
            'evaluation_id' => $evaluation->id,
            'response_type' => 'dispute',
        ]);

        $this->assertDatabaseHas('evaluations', [
            'id' => $evaluation->id,
            'status' => Evaluation::STATUS_AGENT_DISPUTED,
        ]);
    }

    public function test_agent_cannot_respond_to_evaluation_that_is_not_published(): void
    {
        [$agent, $evaluation] = $this->publishedEvaluation();
        $evaluation->update(['status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW]);

        $this->actingAs($agent)
            ->post(route('evaluations.respond', $evaluation), [
                'response_type' => 'accept',
                'commitment_comment' => 'OK',
            ])
            ->assertForbidden();
    }

    public function test_other_agent_cannot_respond_to_evaluation(): void
    {
        [, $evaluation] = $this->publishedEvaluation();
        $otherAgent = $this->userWithRole('agent');

        $this->actingAs($otherAgent)
            ->post(route('evaluations.respond', $evaluation), [
                'response_type' => 'accept',
                'commitment_comment' => 'OK',
            ])
            ->assertForbidden();
    }

    public function test_dispute_requires_reason(): void
    {
        [$agent, $evaluation] = $this->publishedEvaluation();

        $this->actingAs($agent)
            ->post(route('evaluations.respond', $evaluation), [
                'response_type' => 'dispute',
            ])
            ->assertSessionHasErrors(['dispute_reason']);
    }
}
