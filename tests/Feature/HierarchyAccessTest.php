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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HierarchyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_only_sees_explicitly_managed_campaigns(): void
    {
        $manager = $this->userWithRole('manager');
        $managedCampaign = Campaign::create(['name' => 'Managed Campaign']);
        $otherCampaign = Campaign::create(['name' => 'Other Campaign']);

        $manager->managedCampaigns()->attach($managedCampaign);

        $visibleIds = Campaign::forUser($manager)->pluck('id')->all();

        $this->assertContains($managedCampaign->id, $visibleIds);
        $this->assertNotContains($otherCampaign->id, $visibleIds);
    }

    public function test_supervisor_only_sees_own_team_interactions_and_evaluations_inside_shared_campaign(): void
    {
        $admin = $this->userWithRole('admin');
        $supervisor = $this->userWithRole('supervisor');
        $otherSupervisor = $this->userWithRole('supervisor');
        $agent = $this->userWithRole('agent');
        $otherAgent = $this->userWithRole('agent');
        $campaign = Campaign::create(['name' => 'Shared Campaign']);

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

        $ownInteraction = $this->interaction($campaign, $agent, $supervisor, $admin);
        $otherInteraction = $this->interaction($campaign, $otherAgent, $otherSupervisor, $admin);

        $ownEvaluation = $this->evaluation($ownInteraction, $version);
        $otherEvaluation = $this->evaluation($otherInteraction, $version);

        $visibleInteractionIds = Interaction::forUser($supervisor)->pluck('id')->all();
        $visibleEvaluationIds = Evaluation::forUser($supervisor)->pluck('id')->all();

        $this->assertContains($ownInteraction->id, $visibleInteractionIds);
        $this->assertNotContains($otherInteraction->id, $visibleInteractionIds);
        $this->assertContains($ownEvaluation->id, $visibleEvaluationIds);
        $this->assertNotContains($otherEvaluation->id, $visibleEvaluationIds);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function interaction(Campaign $campaign, User $agent, User $supervisor, User $uploadedBy): Interaction
    {
        return Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => now(),
            'uploaded_by' => $uploadedBy->id,
            'file_path' => 'transcripts/test.txt',
            'file_name' => 'test.txt',
            'source_type' => 'text',
            'transcript_text' => 'Test transcript',
            'status' => 'uploaded',
        ]);
    }

    private function evaluation(Interaction $interaction, QualityFormVersion $version): Evaluation
    {
        return Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $interaction->agent_id,
            'type' => 'ai',
            'total_score' => 100,
            'max_possible_score' => 100,
            'percentage_score' => 100,
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
        ]);
    }
}
