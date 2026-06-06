<?php

namespace Tests\Feature;

use App\Jobs\GenerateFeedbackAudioJob;
use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\User;
use App\Services\FeedbackAudioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeedbackAudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_dispatches_feedback_audio_job_only_when_tts_is_enabled(): void
    {
        Queue::fake();

        [$admin, , $disabledEvaluation] = $this->evaluation(Evaluation::STATUS_PENDING_MONITOR_REVIEW);
        config(['ai.feedback_tts.enabled' => false]);

        $this->actingAs($admin)
            ->post(route('evaluations.publish', $disabledEvaluation))
            ->assertRedirect(route('evaluations.show', $disabledEvaluation));

        Queue::assertNotPushed(GenerateFeedbackAudioJob::class);

        [$admin, , $enabledEvaluation] = $this->evaluation(Evaluation::STATUS_PENDING_MONITOR_REVIEW);
        config(['ai.feedback_tts.enabled' => true]);

        $this->actingAs($admin)
            ->post(route('evaluations.publish', $enabledEvaluation))
            ->assertRedirect(route('evaluations.show', $enabledEvaluation));

        Queue::assertPushed(GenerateFeedbackAudioJob::class, fn (GenerateFeedbackAudioJob $job): bool => $job->evaluationId === $enabledEvaluation->id);
        $this->assertSame('pending', $enabledEvaluation->fresh()->feedback_audio_status);
    }

    public function test_feedback_audio_job_stores_mp3_and_marks_evaluation_ready(): void
    {
        Storage::fake('local');
        config([
            'ai.feedback_tts.enabled' => true,
            'ai.feedback_tts.audio_disk' => 'local',
            'ai.feedback_tts.access_token' => 'test-oauth-token',
            'ai.feedback_tts.endpoint' => 'https://texttospeech.googleapis.com/v1beta1/text:synthesize',
        ]);
        Http::fake([
            'https://texttospeech.googleapis.com/v1beta1/text:synthesize' => Http::response([
                'audioContent' => base64_encode('fake-mp3-binary'),
            ]),
        ]);

        [, , $evaluation] = $this->evaluation(Evaluation::STATUS_PUBLISHED_TO_AGENT);
        $evaluation->update([
            'ai_feedback' => [
                'performanceSummary' => 'Atención clara.',
                'productKnowledge' => 'Explica condiciones correctamente.',
                'emotionalHandlingAndEmpathy' => 'Mantiene tono empático.',
                'strengths' => 'Saludo claro; buena escucha',
                'improvementOpportunities' => 'Cerrar con próximos pasos',
            ],
        ]);

        (new GenerateFeedbackAudioJob($evaluation->id))->handle(app(FeedbackAudioService::class));

        $evaluation->refresh();
        $this->assertSame('ready', $evaluation->feedback_audio_status);
        $this->assertSame('local', $evaluation->feedback_audio_disk);
        $this->assertNotNull($evaluation->feedback_audio_generated_at);
        Storage::disk('local')->assertExists($evaluation->feedback_audio_path);
        $this->assertSame('fake-mp3-binary', Storage::disk('local')->get($evaluation->feedback_audio_path));
        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_id' => $evaluation->id,
            'event' => 'feedback_audio_generated',
        ]);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->hasHeader('Authorization', 'Bearer test-oauth-token')
                && ($data['voice']['modelName'] ?? null) === 'gemini-2.5-flash-tts'
                && ($data['audioConfig']['audioEncoding'] ?? null) === 'MP3'
                && str_contains($data['input']['text'] ?? '', 'Atención clara.');
        });
    }

    public function test_feedback_audio_job_can_use_legacy_tts_payload_for_free_tier_voices(): void
    {
        Storage::fake('local');
        config([
            'ai.feedback_tts.enabled' => true,
            'ai.feedback_tts.audio_disk' => 'local',
            'ai.feedback_tts.access_token' => 'test-oauth-token',
            'ai.feedback_tts.endpoint' => 'https://texttospeech.googleapis.com/v1/text:synthesize',
            'ai.feedback_tts.model' => '',
            'ai.feedback_tts.voice' => 'es-US-Standard-B',
            'ai.feedback_tts.language' => 'es-US',
        ]);
        Http::fake([
            'https://texttospeech.googleapis.com/v1/text:synthesize' => Http::response([
                'audioContent' => base64_encode('free-tier-mp3-binary'),
            ]),
        ]);

        [, , $evaluation] = $this->evaluation(Evaluation::STATUS_PUBLISHED_TO_AGENT);
        $evaluation->update([
            'ai_feedback' => [
                'performanceSummary' => 'Atención clara.',
            ],
        ]);

        (new GenerateFeedbackAudioJob($evaluation->id))->handle(app(FeedbackAudioService::class));

        $this->assertSame('ready', $evaluation->fresh()->feedback_audio_status);
        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://texttospeech.googleapis.com/v1/text:synthesize'
                && ! array_key_exists('prompt', $data['input'] ?? [])
                && ! array_key_exists('modelName', $data['voice'] ?? [])
                && ($data['input']['text'] ?? null) === 'Resumen del desempeño: Atención clara.'
                && ($data['voice']['languageCode'] ?? null) === 'es-US'
                && ($data['voice']['name'] ?? null) === 'es-US-Standard-B'
                && ($data['audioConfig']['audioEncoding'] ?? null) === 'MP3';
        });
    }

    public function test_feedback_audio_job_marks_failed_without_throwing(): void
    {
        config([
            'ai.feedback_tts.enabled' => true,
            'ai.feedback_tts.audio_disk' => 'local',
            'ai.feedback_tts.access_token' => 'test-oauth-token',
        ]);
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'quota exceeded']], 429),
        ]);

        [, , $evaluation] = $this->evaluation(Evaluation::STATUS_PUBLISHED_TO_AGENT);
        $evaluation->update([
            'ai_feedback' => [
                'performanceSummary' => 'Atención clara.',
            ],
        ]);

        (new GenerateFeedbackAudioJob($evaluation->id))->handle(app(FeedbackAudioService::class));

        $this->assertSame('failed', $evaluation->fresh()->feedback_audio_status);
        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_id' => $evaluation->id,
            'event' => 'feedback_audio_failed',
        ]);
    }

    public function test_feedback_audio_route_authorizes_and_serves_ready_audio(): void
    {
        Storage::fake('local');

        [$admin, $agent, $evaluation] = $this->evaluation(Evaluation::STATUS_PUBLISHED_TO_AGENT);
        Storage::disk('local')->put('feedbacks/evaluations/test.mp3', 'route-audio');
        $evaluation->update([
            'feedback_audio_status' => 'ready',
            'feedback_audio_disk' => 'local',
            'feedback_audio_path' => 'feedbacks/evaluations/test.mp3',
            'visible_to_agent_at' => now(),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('evaluations.feedback-audio', $evaluation))
            ->assertForbidden();

        $response = $this->actingAs($agent)
            ->get(route('evaluations.feedback-audio', $evaluation))
            ->assertOk();
        $this->assertSame('route-audio', $response->streamedContent());

        $response = $this->actingAs($admin)
            ->get(route('evaluations.feedback-audio', $evaluation))
            ->assertOk();
        $this->assertSame('route-audio', $response->streamedContent());
    }

    private function evaluation(string $status): array
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
            'transcript_text' => 'Agente: Buenos dias. Cliente: Gracias.',
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
            'status' => $status,
            'ai_feedback' => [
                'performanceSummary' => 'Atención clara.',
                'productKnowledge' => 'Explica condiciones correctamente.',
                'emotionalHandlingAndEmpathy' => 'Mantiene tono empático.',
                'strengths' => 'Saludo claro; buena escucha',
                'improvementOpportunities' => 'Cerrar con próximos pasos',
            ],
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
