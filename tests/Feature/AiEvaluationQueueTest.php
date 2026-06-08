<?php

namespace Tests\Feature;

use App\Jobs\ScoreTranscriptJob;
use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityAttribute;
use App\Models\QualityForm;
use App\Models\QualitySubAttribute;
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

    public function test_audio_quality_prompt_includes_voice_and_emotion_signals(): void
    {
        [$admin, $interaction, $version] = $this->scorableInteraction();
        $interaction->update([
            'source_type' => 'audio',
            'audio_duration' => 120,
            'metadata' => [
                'sentiment' => ['overall' => 'mixto', 'summary' => 'Cliente inicia tenso.'],
                'sentiment_segments' => [
                    ['index' => 1, 'start' => 30, 'speaker' => 'client', 'sentiment' => 'negativo', 'emotion' => 'frustracion', 'score' => -0.7, 'tone' => 'molesto', 'pace' => 'rápido'],
                ],
                'acoustic_analysis' => [
                    'agent_speech_rate_wpm' => 138,
                    'client_speech_rate_wpm' => 170,
                    'overall_pace' => 'variable',
                    'interruptions' => 1,
                ],
                'quality_signals' => [
                    'empathy' => 'riesgo',
                    'objection_handling' => 'riesgo',
                    'customer_experience_risk' => 'alto',
                    'summary' => 'Tensión alta requiere validar empatía.',
                ],
            ],
        ]);

        $attribute = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Empatía',
            'weight' => 100,
            'sort_order' => 1,
        ]);
        QualitySubAttribute::create([
            'attribute_id' => $attribute->id,
            'name' => 'Maneja emociones del cliente',
            'weight_percent' => 100,
            'concept' => 'Evalúa tono y empatía.',
            'sort_order' => 1,
        ]);

        $service = new class extends AIEvaluationService {
            public function promptForTest(Interaction $interaction, QualityFormVersion $version): string
            {
                return $this->buildEvaluationPrompt($interaction, $version);
            }
        };

        $prompt = $service->promptForTest($interaction->fresh(), $version->fresh());

        $this->assertStringContainsString('SEÑALES DE AUDIO Y EMOCIÓN', $prompt);
        $this->assertStringContainsString('"ac":{"ar":138,"cr":170,"pace":"variable","int":1}', $prompt);
        $this->assertStringContainsString('"sig":{"emp":"riesgo","obj":"riesgo","cx":"alto","sum":"Tensión alta requiere validar empatía."}', $prompt);
        $this->assertStringContainsString('Tensión alta requiere validar empatía.', $prompt);
        $this->assertStringNotContainsString('agent_speech_rate_wpm', $prompt);
        $this->assertStringNotContainsString('customer_experience_risk', $prompt);
    }

    public function test_evaluation_prompt_removes_golden_records_and_uses_compact_static_context(): void
    {
        [, $interaction, $version] = $this->scorableInteraction();

        $gold = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $interaction->agent_id,
            'type' => 'ai',
            'total_score' => 100,
            'max_possible_score' => 100,
            'percentage_score' => 100,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'is_gold' => true,
            'ai_summary' => 'GOLDEN RECORD QUE NO DEBE ENTRAR AL PROMPT',
        ]);
        $gold->items()->create([
            'subattribute_id' => $version->formAttributes()->first()->subAttributes()->first()->id,
            'status' => 'compliant',
            'score' => 1,
            'max_score' => 1,
            'weighted_score' => 100,
            'confidence' => 1,
            'evidence_quote' => 'Golden evidence',
            'ai_notes' => 'Golden notes',
        ]);

        $version->formAttributes()->first()->subAttributes()->create([
            'name' => 'Evita placeholders',
            'weight_percent' => 0,
            'concept' => 'Sin descripción',
            'sort_order' => 2,
        ]);

        $service = new class extends AIEvaluationService {
            public function promptForTest(Interaction $interaction, QualityFormVersion $version): string
            {
                return $this->buildEvaluationPrompt($interaction, $version);
            }
        };

        $prompt = $service->promptForTest($interaction->fresh(), $version->fresh());

        $this->assertStringNotContainsString('GOLDEN RECORD', $prompt);
        $this->assertStringNotContainsString('Golden evidence', $prompt);
        $this->assertStringNotContainsString('Sin descripción', $prompt);
        $this->assertStringContainsString('No infieras hechos', $prompt);
        $this->assertStringContainsString('Máximo 400 caracteres', $prompt);
        $this->assertStringContainsString('## CONTEXTO OPERATIVO', $prompt);
        $this->assertStringContainsString('"id":', $prompt);
        $this->assertMatchesRegularExpression('/\[\{"id":\d+,"a":"Saludo","n":"Saluda al cliente"/', $prompt);
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
            'operational_context_markdown' => "Producto A cuesta 10.\n\n\nDebe validarse identidad.",
            'created_by' => $admin->id,
        ]);
        $version = QualityFormVersion::create([
            'quality_form_id' => $form->id,
            'version_number' => 1,
            'status' => 'published',
        ]);
        $attribute = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Saludo',
            'weight' => 100,
            'sort_order' => 1,
        ]);
        QualitySubAttribute::create([
            'attribute_id' => $attribute->id,
            'name' => 'Saluda al cliente',
            'weight_percent' => 100,
            'concept' => 'Valida si el agente saluda.',
            'sort_order' => 1,
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
