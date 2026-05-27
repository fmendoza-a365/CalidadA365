<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\User;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiPerformanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_ai_performance_dashboard(): void
    {
        [$admin] = $this->aiEvaluation();

        $this->actingAs($admin)
            ->get(route('settings.ai.performance'))
            ->assertOk()
            ->assertSee('Rendimiento IA')
            ->assertSee('simulated')
            ->assertSee(AiSettings::PROMPT_VERSION);
    }

    public function test_user_with_ai_performance_permission_can_view_dashboard(): void
    {
        $this->aiEvaluation();
        Permission::firstOrCreate(['name' => 'view_ai_performance', 'guard_name' => 'web']);

        $user = $this->userWithRole('qa_manager');
        $user->givePermissionTo('view_ai_performance');

        $this->actingAs($user)
            ->get(route('settings.ai.performance'))
            ->assertOk()
            ->assertSee('Rendimiento IA');
    }

    private function aiEvaluation(): array
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
            'ai_processed_at' => now(),
            'ai_provider' => 'simulated',
            'ai_model' => 'simulated',
            'ai_prompt_version' => AiSettings::PROMPT_VERSION,
        ]);

        return [$admin];
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
