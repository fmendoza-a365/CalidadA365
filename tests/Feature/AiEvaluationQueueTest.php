<?php

namespace Tests\Feature;

use App\Jobs\ScoreTranscriptJob;
use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\User;
use App\Services\AIEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiEvaluationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_ai_evaluation_creates_visible_pending_record_immediately(): void
    {
        Queue::fake();

        [$admin, $interaction, $version] = $this->scorableInteraction();

        $this->actingAs($admin)
            ->post(route('transcripts.evaluate', $interaction))
            ->assertRedirect(route('transcripts.show', $interaction));

        Queue::assertPushedOn('ai-scoring', ScoreTranscriptJob::class);

        $this->assertDatabaseHas('evaluations', [
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'type' => 'ai',
            'status' => Evaluation::STATUS_PENDING_AI,
        ]);

        $evaluation = Evaluation::where('interaction_id', $interaction->id)->firstOrFail();
        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_id' => $evaluation->id,
            'actor_id' => $admin->id,
            'event' => 'ai_queued',
            'from_status' => null,
            'to_status' => Evaluation::STATUS_PENDING_AI,
        ]);

        $this->actingAs($admin)
            ->get(route('evaluations.index'))
            ->assertOk()
            ->assertSee(Evaluation::statusLabel(Evaluation::STATUS_PENDING_AI));
    }

    public function test_score_job_completes_existing_pending_ai_evaluation(): void
    {
        [$admin, $interaction, $version] = $this->scorableInteraction();

        $evaluation = Evaluation::createPendingAiForInteraction($interaction, $version);

        (new ScoreTranscriptJob($interaction->id))->handle(new AIEvaluationService);

        $this->assertSame(Evaluation::STATUS_PENDING_MONITOR_REVIEW, $evaluation->fresh()->status);
        $this->assertSame('scored', $interaction->fresh()->status);
        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_id' => $evaluation->id,
            'event' => 'ai_processing_started',
            'from_status' => Evaluation::STATUS_PENDING_AI,
            'to_status' => Evaluation::STATUS_AI_PROCESSING,
        ]);
        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_id' => $evaluation->id,
            'event' => 'ai_evaluated',
            'from_status' => Evaluation::STATUS_AI_PROCESSING,
            'to_status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
        ]);
        $this->assertDatabaseHas('evaluations', [
            'id' => $evaluation->id,
            'ai_provider' => 'simulated',
            'ai_model' => 'simulated',
            'ai_prompt_version' => \App\Support\AiSettings::PROMPT_VERSION,
        ]);
        $this->assertNotNull($evaluation->fresh()->ai_prompt_hash);
        $this->assertArrayNotHasKey('api_key', $evaluation->fresh()->ai_settings_snapshot['config']);
    }

    private function scorableInteraction(): array
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
        $campaign->update(['active_form_version_id' => $version->id]);
        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'transcripts/test.txt',
            'file_name' => 'test.txt',
            'source_type' => 'text',
            'transcript_text' => 'Agente: Buenos dias. Cliente: Gracias.',
            'status' => 'uploaded',
        ]);

        return [$admin, $interaction, $version];
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_evaluations', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        if ($role === 'admin') {
            $user->givePermissionTo('create_evaluations');
        }

        return $user;
    }
}
