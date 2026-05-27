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

    public function test_agent_cannot_access_work_queue(): void
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->get(route('work-queue.index'))
            ->assertForbidden();
    }

    private function pendingEvaluation(): array
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
        Evaluation::create([
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

        return [$admin, $agent];
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
