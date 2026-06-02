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

class EvaluationWorkQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_user_sees_pending_evaluations_in_work_queue(): void
    {
        [$admin] = $this->pendingEvaluation();

        $this->actingAs($admin)
            ->get(route('work-queue.index'))
            ->assertOk()
            ->assertSee('Bandeja Operativa')
            ->assertSee('Productividad Operativa')
            ->assertSee('Revisión monitor')
            ->assertSee('Campaign');
    }

    public function test_work_queue_paginates_pending_reviews_and_skips_manually_corrected_interactions(): void
    {
        [$admin] = $this->pendingEvaluation('Visible Agent 01');

        foreach (range(2, 10) as $index) {
            $this->pendingEvaluation('Visible Agent '.str_pad((string) $index, 2, '0', STR_PAD_LEFT));
        }

        [, , $hiddenEvaluation, $hiddenInteraction, $version] = $this->pendingEvaluation('Hidden Corrected Agent');

        Evaluation::create([
            'interaction_id' => $hiddenInteraction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $hiddenEvaluation->campaign_id,
            'agent_id' => $hiddenEvaluation->agent_id,
            'type' => 'manual',
            'evaluator_id' => $admin->id,
            'total_score' => 90,
            'max_possible_score' => 100,
            'percentage_score' => 90,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'visible_to_agent_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('work-queue.index'))
            ->assertOk()
            ->assertSee('1-8 de 10')
            ->assertSee('Visible Agent')
            ->assertDontSee('Hidden Corrected Agent');
    }

    public function test_agent_cannot_access_work_queue(): void
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->get(route('work-queue.index'))
            ->assertForbidden();
    }

    public function test_work_queue_hides_reviews_claimed_by_another_monitor(): void
    {
        [, , $evaluation] = $this->pendingEvaluation('Claimed Agent');
        $claimingMonitor = $this->userWithRole('qa_monitor');
        $otherMonitor = $this->userWithRole('qa_monitor');
        $claimingMonitor->managedCampaigns()->attach($evaluation->campaign_id);
        $otherMonitor->managedCampaigns()->attach($evaluation->campaign_id);

        $evaluation->forceFill([
            'review_claimed_by' => $claimingMonitor->id,
            'review_claimed_at' => now(),
            'review_claim_expires_at' => now()->addMinutes(30),
        ])->save();

        $this->actingAs($otherMonitor)
            ->get(route('work-queue.index'))
            ->assertOk()
            ->assertDontSee('Claimed Agent');

        $this->actingAs($claimingMonitor)
            ->get(route('work-queue.index'))
            ->assertOk()
            ->assertSee('Claimed Agent')
            ->assertSee('Reservado por ti');
    }

    public function test_expired_review_claim_is_available_to_another_monitor(): void
    {
        [, , $evaluation] = $this->pendingEvaluation('Expired Claim Agent');
        $claimingMonitor = $this->userWithRole('qa_monitor');
        $otherMonitor = $this->userWithRole('qa_monitor');
        $claimingMonitor->managedCampaigns()->attach($evaluation->campaign_id);
        $otherMonitor->managedCampaigns()->attach($evaluation->campaign_id);

        $evaluation->forceFill([
            'review_claimed_by' => $claimingMonitor->id,
            'review_claimed_at' => now()->subHour(),
            'review_claim_expires_at' => now()->subMinute(),
        ])->save();

        $this->actingAs($otherMonitor)
            ->get(route('work-queue.index'))
            ->assertOk()
            ->assertSee('Expired Claim Agent')
            ->assertDontSee('Reservado por ti');
    }

    private function pendingEvaluation(string $agentName = 'Agent'): array
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $agent->forceFill(['name' => $agentName])->save();
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
            'type' => 'ai',
            'total_score' => 90,
            'max_possible_score' => 100,
            'percentage_score' => 90,
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
        ]);

        return [$admin, $agent, $evaluation, $interaction, $version];
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
