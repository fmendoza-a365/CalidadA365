<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\EvaluationAuditEvent;
use App\Models\Interaction;
use App\Models\QualityAttribute;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\QualitySubAttribute;
use App\Models\Setting;
use App\Models\User;
use App\Services\AIEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiContextCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_explicit_cache_is_created_used_and_audited(): void
    {
        [$interaction, $version, $subAttribute] = $this->scorableInteraction();
        $this->configureGemini();

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:countTokens*' => Http::response(['totalTokens' => 1500]),
            'https://generativelanguage.googleapis.com/v1beta/cachedContents*' => Http::response([
                'name' => 'cachedContents/static-context-1',
                'expireTime' => now()->addHours(2)->toIso8601String(),
            ]),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent*' => Http::response(
                $this->geminiEvaluationResponse($subAttribute->id, [
                    'cachedContentTokenCount' => 1500,
                    'promptTokenCount' => 320,
                    'candidatesTokenCount' => 90,
                    'totalTokenCount' => 1910,
                ])
            ),
        ]);

        $evaluation = (new AIEvaluationService)->evaluateInteraction($interaction);

        $this->assertInstanceOf(Evaluation::class, $evaluation);
        $version->refresh();
        $this->assertSame('cachedContents/static-context-1', $version->gemini_cache_id);
        $this->assertSame(1500, $version->gemini_cache_token_count);
        $this->assertNotNull($version->gemini_cache_hash);
        $this->assertNotNull($version->gemini_cache_expires_at);

        $audit = EvaluationAuditEvent::where('evaluation_id', $evaluation->id)
            ->where('event', 'ai_evaluated')
            ->firstOrFail();

        $this->assertTrue($audit->metadata['gemini_cache_used']);
        $this->assertSame('created', $audit->metadata['gemini_cache_status']);
        $this->assertSame(1500, $audit->metadata['gemini_cache_token_count']);
        $this->assertSame(1500, $audit->metadata['gemini_cached_content_token_count']);
        $this->assertSame(320, $audit->metadata['prompt_token_count']);
        $this->assertSame(90, $audit->metadata['candidates_token_count']);

        $generateRequests = $this->recordedGeminiGenerateRequests();
        $this->assertCount(1, $generateRequests);
        $requestData = $generateRequests->first()[0]->data();
        $this->assertSame('cachedContents/static-context-1', $requestData['cachedContent'] ?? null);
        $this->assertStringContainsString('## TRANSCRIPCIÓN', $requestData['contents'][0]['parts'][0]['text']);
        $this->assertStringNotContainsString('## CRITERIOS DE EVALUACIÓN', $requestData['contents'][0]['parts'][0]['text']);
    }

    public function test_invalid_gemini_cache_is_cleared_and_retried_once_without_cache(): void
    {
        [$interaction, $version, $subAttribute] = $this->scorableInteraction();
        $this->configureGemini();

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:countTokens*' => Http::response(['totalTokens' => 1500]),
            'https://generativelanguage.googleapis.com/v1beta/cachedContents*' => Http::response([
                'name' => 'cachedContents/stale-context',
                'expireTime' => now()->addHours(2)->toIso8601String(),
            ]),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent*' => Http::sequence()
                ->push(['error' => ['message' => 'CachedContent not found']], 400)
                ->push($this->geminiEvaluationResponse($subAttribute->id, [
                    'promptTokenCount' => 1800,
                    'candidatesTokenCount' => 80,
                    'totalTokenCount' => 1880,
                ])),
        ]);

        $evaluation = (new AIEvaluationService)->evaluateInteraction($interaction);

        $this->assertInstanceOf(Evaluation::class, $evaluation);
        $version->refresh();
        $this->assertNull($version->gemini_cache_id);
        $this->assertNull($version->gemini_cache_hash);
        $this->assertNull($version->gemini_cache_token_count);

        $audit = EvaluationAuditEvent::where('evaluation_id', $evaluation->id)
            ->where('event', 'ai_evaluated')
            ->firstOrFail();

        $this->assertFalse($audit->metadata['gemini_cache_used']);
        $this->assertSame('invalid_retry_full', $audit->metadata['gemini_cache_status']);
        $this->assertSame(1800, $audit->metadata['prompt_token_count']);

        $generateRequests = $this->recordedGeminiGenerateRequests();
        $this->assertCount(2, $generateRequests);
        $this->assertSame('cachedContents/stale-context', $generateRequests[0][0]->data()['cachedContent'] ?? null);
        $this->assertArrayNotHasKey('cachedContent', $generateRequests[1][0]->data());
        $this->assertStringContainsString('## CRITERIOS DE EVALUACIÓN', $generateRequests[1][0]->data()['contents'][0]['parts'][0]['text']);
    }

    public function test_unknown_gemini_model_skips_explicit_cache_without_manual_minimum(): void
    {
        [$interaction, $version, $subAttribute] = $this->scorableInteraction();
        $this->configureGemini('gemini-unknown-model');

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-unknown-model:countTokens*' => Http::response(['totalTokens' => 9000]),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-unknown-model:generateContent*' => Http::response(
                $this->geminiEvaluationResponse($subAttribute->id, [
                    'promptTokenCount' => 9000,
                    'candidatesTokenCount' => 60,
                    'totalTokenCount' => 9060,
                ])
            ),
        ]);

        $evaluation = (new AIEvaluationService)->evaluateInteraction($interaction);

        $this->assertInstanceOf(Evaluation::class, $evaluation);
        $this->assertNull($version->fresh()->gemini_cache_id);
        $this->assertCount(1, $this->recordedGeminiGenerateRequests());

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/cachedContents'));
    }

    private function configureGemini(string $model = 'gemini-2.5-flash'): void
    {
        Setting::set('ai.provider', 'gemini', 'string', 'ai');
        Setting::set('ai.gemini_api_key', 'test-gemini-key', 'string', 'ai');
        Setting::set('ai.gemini_model', $model, 'string', 'ai');
        Setting::set('ai.gemini_temperature', 0.0, 'float', 'ai');
    }

    private function scorableInteraction(): array
    {
        $admin = User::factory()->create();
        $agent = User::factory()->create();
        $supervisor = User::factory()->create();
        $campaign = Campaign::create(['name' => 'Campaign']);
        $form = QualityForm::create([
            'campaign_id' => $campaign->id,
            'name' => 'Quality Form',
            'operational_context_markdown' => str_repeat('Regla operativa verificable. ', 80),
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
        $subAttribute = QualitySubAttribute::create([
            'attribute_id' => $attribute->id,
            'name' => 'Saluda al cliente',
            'weight_percent' => 100,
            'concept' => 'Debe saludar con cortesía.',
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
            'transcript_text' => 'Agente: Buenos días, gracias por llamar. Cliente: Necesito ayuda.',
            'status' => 'uploaded',
        ]);

        return [$interaction, $version, $subAttribute];
    }

    private function geminiEvaluationResponse(int $subAttributeId, array $usageMetadata = []): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'items' => [
                                        [
                                            'id' => $subAttributeId,
                                            'status' => 'compliant',
                                            'evidence_quote' => 'Agente: Buenos días, gracias por llamar.',
                                            'confidence' => 0.97,
                                            'notes' => 'Cumple el saludo inicial.',
                                        ],
                                    ],
                                    'feedback' => [
                                        'performanceSummary' => 'Gestión clara y cordial.',
                                        'productKnowledge' => 'No hubo datos de producto que validar.',
                                        'emotionalHandlingAndEmpathy' => 'Mantiene tono amable.',
                                        'strengths' => 'Saludo claro; tono cordial',
                                        'improvementOpportunities' => 'Profundizar validación de necesidad',
                                    ],
                                ], JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => $usageMetadata,
        ];
    }

    private function recordedGeminiGenerateRequests()
    {
        return collect(Http::recorded())
            ->filter(fn (array $record): bool => str_contains($record[0]->url(), ':generateContent'))
            ->values();
    }
}
