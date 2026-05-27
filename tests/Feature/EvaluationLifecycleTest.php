<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EvaluationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_user_can_close_and_reopen_evaluation_with_audit_events(): void
    {
        [$admin, , $evaluation] = $this->publishedEvaluation();

        $this->actingAs($admin)
            ->post(route('evaluations.close', $evaluation), [
                'closure_reason' => 'Ciclo operativo terminado.',
            ])
            ->assertRedirect(route('evaluations.show', $evaluation));

        $this->assertDatabaseHas('evaluations', [
            'id' => $evaluation->id,
            'status' => Evaluation::STATUS_CLOSED,
            'previous_status_before_close' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'closed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_id' => $evaluation->id,
            'actor_id' => $admin->id,
            'event' => 'closed',
            'from_status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'to_status' => Evaluation::STATUS_CLOSED,
        ]);

        $this->actingAs($admin)
            ->post(route('evaluations.reopen', $evaluation->fresh()))
            ->assertRedirect(route('evaluations.show', $evaluation));

        $this->assertDatabaseHas('evaluations', [
            'id' => $evaluation->id,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'previous_status_before_close' => null,
            'closed_by' => null,
        ]);
        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_id' => $evaluation->id,
            'actor_id' => $admin->id,
            'event' => 'reopened',
            'from_status' => Evaluation::STATUS_CLOSED,
            'to_status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
        ]);
    }

    public function test_closed_evaluation_cannot_receive_agent_response(): void
    {
        [$admin, $agent, $evaluation] = $this->publishedEvaluation();
        $this->actingAs($admin)->post(route('evaluations.close', $evaluation));

        $this->actingAs($agent)
            ->post(route('evaluations.respond', $evaluation->fresh()), [
                'response_type' => 'accept',
                'commitment_comment' => 'Entendido.',
            ])
            ->assertForbidden();
    }

    private function publishedEvaluation(): array
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $supervisor = $this->userWithRole('supervisor');
        $campaign = Campaign::create(['name' => 'Campaign']);
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
        $evaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'type' => 'manual',
            'evaluator_id' => $admin->id,
            'total_score' => 100,
            'max_possible_score' => 100,
            'percentage_score' => 100,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'visible_to_agent_at' => now(),
        ]);

        return [$admin, $agent, $evaluation];
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
