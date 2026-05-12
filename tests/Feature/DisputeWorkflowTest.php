<?php

namespace Tests\Feature;

use App\Models\AgentResponse;
use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\DisputeResolution;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DisputeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispute_moves_through_exception_escalation_flow(): void
    {
        $admin = $this->userWithRole('admin');
        $qaManager = $this->userWithRole('qa_manager');
        $coordinator = $this->userWithRole('qa_coordinator');
        $monitor = $this->userWithRole('qa_monitor');
        $supervisor = $this->userWithRole('supervisor');
        $agent = $this->userWithRole('agent');
        $campaign = Campaign::create(['name' => 'Campaign']);

        $monitor->managedCampaigns()->attach($campaign);
        $coordinator->managedCampaigns()->attach($campaign);

        CampaignUserAssignment::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        $evaluation = $this->evaluation($campaign, $agent, $supervisor, $admin);
        $response = AgentResponse::create([
            'evaluation_id' => $evaluation->id,
            'agent_id' => $agent->id,
            'response_type' => 'dispute',
            'dispute_reason' => 'No estoy de acuerdo con la evidencia.',
        ]);
        $dispute = DisputeResolution::create([
            'agent_response_id' => $response->id,
            'evaluation_id' => $evaluation->id,
            'status' => DisputeResolution::STATUS_PENDING_SUPERVISOR_REVIEW,
        ]);

        $this->actingAs($supervisor)
            ->post(route('disputes.supervisor-review', $dispute), [
                'supervisor_notes' => 'El asesor siguió el speech operativo indicado.',
            ])
            ->assertRedirect(route('evaluations.show', $evaluation));

        $this->assertDatabaseHas('dispute_resolutions', [
            'id' => $dispute->id,
            'status' => DisputeResolution::STATUS_PENDING_QA_REVIEW,
            'supervisor_reviewed_by' => $supervisor->id,
        ]);

        $this->actingAs($monitor)
            ->post(route('disputes.qa-review', $dispute->fresh()), [
                'qa_recommendation' => 'partial',
                'qa_notes' => 'Corresponde ajustar parcialmente la observación.',
            ])
            ->assertRedirect(route('evaluations.show', $evaluation));

        $this->assertDatabaseHas('dispute_resolutions', [
            'id' => $dispute->id,
            'status' => DisputeResolution::STATUS_PENDING_COORDINATOR_REVIEW,
            'qa_reviewed_by' => $monitor->id,
        ]);

        $this->actingAs($coordinator)
            ->post(route('disputes.coordinator-review', $dispute->fresh()), [
                'coordinator_decision' => 'validated',
                'coordinator_notes' => 'Se valida la recomendación del monitor.',
            ])
            ->assertRedirect(route('evaluations.show', $evaluation));

        $this->assertDatabaseHas('dispute_resolutions', [
            'id' => $dispute->id,
            'status' => DisputeResolution::STATUS_READY_MANAGER_RESOLUTION,
            'coordinator_reviewed_by' => $coordinator->id,
        ]);

        $this->actingAs($qaManager)
            ->post(route('disputes.resolve', $dispute->fresh()), [
                'resolution_decision' => 'partial',
                'resolution_notes' => 'Se ajusta la nota final por evidencia insuficiente.',
                'adjusted_score' => 92,
            ])
            ->assertRedirect(route('evaluations.show', $evaluation));

        $this->assertDatabaseHas('dispute_resolutions', [
            'id' => $dispute->id,
            'status' => DisputeResolution::STATUS_RESOLVED,
            'resolved_by' => $qaManager->id,
        ]);
        $this->assertDatabaseHas('evaluations', [
            'id' => $evaluation->id,
            'status' => Evaluation::STATUS_DISPUTE_RESOLVED,
            'percentage_score' => 92,
        ]);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function evaluation(Campaign $campaign, User $agent, User $supervisor, User $admin): Evaluation
    {
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
            'transcript_text' => 'Test transcript',
            'status' => 'uploaded',
        ]);

        return Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'type' => 'ai',
            'total_score' => 100,
            'max_possible_score' => 100,
            'percentage_score' => 100,
            'status' => Evaluation::STATUS_AGENT_DISPUTED,
        ]);
    }
}
