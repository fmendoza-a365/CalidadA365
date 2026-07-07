<?php

namespace Tests\Feature;

use App\Jobs\TranscribeAudioJob;
use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\Interaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\AudioTranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TranscriptUploadMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_stores_call_sn_metadata(): void
    {
        Storage::fake('local');

        $admin = $this->userWithRoleAndPermissions('admin', ['create_transcripts', 'view_transcripts']);
        $agent = $this->userWithRoleAndPermissions('agent');
        $supervisor = $this->userWithRoleAndPermissions('supervisor');

        $campaign = Campaign::create([
            'name' => 'Campaña QA',
            'is_active' => true,
        ]);

        CampaignUserAssignment::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('transcripts.create'))
            ->assertOk()
            ->assertSee('Clasificación de la Interacción')
            ->assertSee('ID externo')
            ->assertSee('Evaluación IA');

        $file = UploadedFile::fake()->create('llamada.txt', 1, 'text/plain');

        $this->actingAs($admin)
            ->post(route('transcripts.store'), [
                'campaign_id' => $campaign->id,
                'agent_id' => $agent->id,
                'occurred_at' => now()->format('Y-m-d\TH:i'),
                'call_sn' => ' SN-ABC-123 ',
                'external_id' => ' CRM-987 ',
                'channel' => 'call',
                'direction' => 'inbound',
                'language' => 'es',
                'contact_reason' => ' Reclamo por activación ',
                'outcome' => 'resolved',
                'customer_reference' => '***1234',
                'queue_name' => 'Soporte premium',
                'product_name' => 'Cuenta digital',
                'priority' => 'complaint',
                'tags' => 'reclamo, activacion, reclamo',
                'diarization_mode' => 'auto',
                'analyze_emotion' => '1',
                'detect_critical_compliance' => '1',
                'ai_context' => 'Validar manejo de reclamo.',
                'transcript_files' => [$file],
            ])
            ->assertRedirect(route('transcripts.index'));

        $this->assertDatabaseHas('interactions', [
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'call_sn' => 'SN-ABC-123',
            'external_id' => 'CRM-987',
            'channel' => 'call',
            'direction' => 'inbound',
            'contact_reason' => 'Reclamo por activación',
            'outcome' => 'resolved',
            'priority' => 'complaint',
            'file_name' => 'llamada.txt',
        ]);

        $interaction = Interaction::firstOrFail();

        $this->assertSame(['reclamo', 'activacion'], $interaction->metadata['upload']['tags']);
        $this->assertSame('manual_upload', $interaction->metadata['upload']['origin']);
        $this->assertTrue($interaction->metadata['upload']['analysis_options']['emotion']);

        $this->actingAs($admin)
            ->get(route('transcripts.show', $interaction))
            ->assertOk()
            ->assertSee('SN-ABC-123')
            ->assertSee('Reclamo por activación')
            ->assertSee('Español');

        $this->actingAs($admin)
            ->get(route('transcripts.index', ['search' => 'CRM-987', 'channel' => 'call', 'priority' => 'complaint']))
            ->assertOk()
            ->assertSee('SN-ABC-123')
            ->assertSee('CRM-987');

        $this->actingAs($admin)
            ->get(route('transcripts.index', ['search' => (string) $interaction->id]))
            ->assertOk()
            ->assertSee('SN-ABC-123')
            ->assertSee('CRM-987');

        $interaction->update(['call_sn' => '178136128306096444815426329481']);

        $this->actingAs($admin)
            ->get(route('transcripts.index', ['search' => '178136128306096444815426329481']))
            ->assertOk()
            ->assertSee('178136128306096444815426329481');
    }

    public function test_audio_transcription_derives_duration_from_wav_file(): void
    {
        Storage::fake('local');

        $admin = $this->userWithRoleAndPermissions('admin');
        $campaign = Campaign::create([
            'name' => 'Campaña Audio',
            'is_active' => true,
        ]);

        Storage::disk('local')->put('audios/sample.wav', $this->wavBytes(3));

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $admin->id,
            'supervisor_id' => $admin->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'audios/sample.wav',
            'file_name' => 'sample.wav',
            'source_type' => 'audio',
            'transcription_status' => 'pending',
            'transcript_text' => '',
            'status' => 'uploaded',
        ]);

        (new TranscribeAudioJob($interaction->id))->handle(app(AudioTranscriptionService::class));

        $interaction->refresh();

        $this->assertSame(3, $interaction->audio_duration);
        $this->assertSame('completed', $interaction->transcription_status);
        $this->assertSame(2, $interaction->metadata['audio_analysis_version']);
        $this->assertNotEmpty($interaction->metadata['sentiment_segments']);
        $this->assertSame('normal', $interaction->metadata['acoustic_analysis']['overall_pace']);
        $this->assertSame(1, $interaction->metadata['acoustic_analysis']['long_pauses']);
        $this->assertEqualsWithDelta(3.0, $interaction->metadata['acoustic_analysis']['dead_air_total_seconds'], 0.2);
        $this->assertSame('fortaleza', $interaction->metadata['quality_signals']['empathy']);
    }

    public function test_audio_transcription_repairs_plain_transcript_response_with_required_analysis(): void
    {
        Storage::fake('local');
        Setting::set('ai.gemini_api_key', 'test-gemini-key', 'string', 'ai');

        Http::fakeSequence()
            ->push($this->geminiTextResponse("[00:00] Agente: Hola, ¿en qué puedo ayudarle?\n[00:04] Cliente: Tengo un reclamo."), 200)
            ->push($this->geminiTextResponse(json_encode($this->analysisPayload(), JSON_UNESCAPED_UNICODE)), 200);

        $admin = $this->userWithRoleAndPermissions('admin');
        $campaign = Campaign::create([
            'name' => 'Campaña Audio',
            'is_active' => true,
        ]);

        Storage::disk('local')->put('audios/plain-response.wav', $this->wavBytes(3));

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $admin->id,
            'supervisor_id' => $admin->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'audios/plain-response.wav',
            'file_name' => 'plain-response.wav',
            'source_type' => 'audio',
            'transcription_status' => 'pending',
            'transcript_text' => '',
            'status' => 'uploaded',
        ]);

        (new TranscribeAudioJob($interaction->id))->handle(app(AudioTranscriptionService::class));

        $interaction->refresh();

        $this->assertSame('completed', $interaction->transcription_status);
        $this->assertStringContainsString('Tengo un reclamo', $interaction->transcript_text);
        $this->assertSame('mixto', $interaction->metadata['sentiment']['overall']);
        $this->assertNotEmpty($interaction->metadata['sentiment_segments']);
        $this->assertSame('variable', $interaction->metadata['acoustic_analysis']['overall_pace']);
        $this->assertSame('riesgo', $interaction->metadata['quality_signals']['empathy']);

        Http::assertSentCount(2);
    }

    public function test_audio_transcription_remains_pending_when_required_analysis_is_missing(): void
    {
        Storage::fake('local');
        Setting::set('ai.gemini_api_key', 'test-gemini-key', 'string', 'ai');

        Http::fakeSequence()
            ->push($this->geminiTextResponse(json_encode(['transcript' => '[00:00] Agente: Hola'], JSON_UNESCAPED_UNICODE)), 200)
            ->push($this->geminiTextResponse(json_encode(['sentiment_segments' => []], JSON_UNESCAPED_UNICODE)), 200);

        $admin = $this->userWithRoleAndPermissions('admin');
        $campaign = Campaign::create([
            'name' => 'Campaña Audio',
            'is_active' => true,
        ]);

        Storage::disk('local')->put('audios/missing-analysis.wav', $this->wavBytes(3));

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $admin->id,
            'supervisor_id' => $admin->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'audios/missing-analysis.wav',
            'file_name' => 'missing-analysis.wav',
            'source_type' => 'audio',
            'transcription_status' => 'pending',
            'transcript_text' => '',
            'status' => 'uploaded',
        ]);

        (new TranscribeAudioJob($interaction->id))->handle(app(AudioTranscriptionService::class));

        $interaction->refresh();

        $this->assertSame('pending', $interaction->transcription_status);
        $this->assertSame('', $interaction->transcript_text);
        $this->assertEmpty($interaction->metadata['sentiment'] ?? null);

        Http::assertSentCount(2);
    }

    public function test_audio_transcription_stores_technical_dead_air_metrics(): void
    {
        Storage::fake('local');

        $admin = $this->userWithRoleAndPermissions('admin');
        $campaign = Campaign::create([
            'name' => 'Campaña Audio',
            'is_active' => true,
        ]);

        Storage::disk('local')->put('audios/dead-air.wav', $this->wavBytesWithToneAndSilence());

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $admin->id,
            'supervisor_id' => $admin->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'audios/dead-air.wav',
            'file_name' => 'dead-air.wav',
            'source_type' => 'audio',
            'transcription_status' => 'pending',
            'transcript_text' => '',
            'status' => 'uploaded',
        ]);

        (new TranscribeAudioJob($interaction->id))->handle(app(AudioTranscriptionService::class));

        $interaction->refresh();
        $acoustic = $interaction->metadata['acoustic_analysis'];

        $this->assertSame(5, $interaction->audio_duration);
        $this->assertSame(1, $acoustic['long_pauses']);
        $this->assertEqualsWithDelta(3.0, $acoustic['dead_air_total_seconds'], 0.2);
        $this->assertEqualsWithDelta(0.6, $acoustic['silence_ratio'], 0.05);
        $this->assertNotEmpty($acoustic['dead_air_segments']);
        $this->assertEqualsWithDelta(1.0, $acoustic['dead_air_segments'][0]['start'], 0.2);
        $this->assertEqualsWithDelta(4.0, $acoustic['dead_air_segments'][0]['end'], 0.2);
    }

    public function test_audio_transcription_uses_wav_fact_chunk_for_compressed_audio_duration(): void
    {
        Storage::fake('local');

        $admin = $this->userWithRoleAndPermissions('admin');
        $campaign = Campaign::create([
            'name' => 'Campaña Audio',
            'is_active' => true,
        ]);

        Storage::disk('local')->put('audios/compressed.wav', $this->compressedWavBytesWithFactDuration(4));

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $admin->id,
            'supervisor_id' => $admin->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'audios/compressed.wav',
            'file_name' => 'compressed.wav',
            'source_type' => 'audio',
            'transcription_status' => 'pending',
            'transcript_text' => '',
            'status' => 'uploaded',
        ]);

        (new TranscribeAudioJob($interaction->id))->handle(app(AudioTranscriptionService::class));

        $interaction->refresh();

        $this->assertSame(4, $interaction->audio_duration);
        $this->assertSame('completed', $interaction->transcription_status);
    }

    public function test_audio_transcription_ignores_misleading_wav_fact_chunk(): void
    {
        Storage::fake('local');

        $admin = $this->userWithRoleAndPermissions('admin');
        $campaign = Campaign::create([
            'name' => 'Campaña Audio',
            'is_active' => true,
        ]);

        Storage::disk('local')->put('audios/misleading-fact.wav', $this->compressedWavBytesWithFactDuration(16, 4));

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $admin->id,
            'supervisor_id' => $admin->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'audios/misleading-fact.wav',
            'file_name' => 'misleading-fact.wav',
            'source_type' => 'audio',
            'transcription_status' => 'pending',
            'transcript_text' => '',
            'status' => 'uploaded',
        ]);

        (new TranscribeAudioJob($interaction->id))->handle(app(AudioTranscriptionService::class));

        $interaction->refresh();

        $this->assertSame(4, $interaction->audio_duration);
        $this->assertSame('completed', $interaction->transcription_status);
    }

    public function test_create_view_passes_supervisors_by_campaign_and_full_names(): void
    {
        $admin = $this->userWithRoleAndPermissions('admin', ['create_transcripts']);
        $agent = $this->userWithRoleAndPermissions('agent');
        $agent->update([
            'name' => 'AgentName',
            'paternal_surname' => 'Paternal',
            'maternal_surname' => 'Maternal',
        ]);
        $supervisor = $this->userWithRoleAndPermissions('supervisor');
        $supervisor->update([
            'name' => 'SuperName',
            'paternal_surname' => 'SuperPat',
            'maternal_surname' => 'SuperMat',
        ]);

        $campaign = Campaign::create([
            'name' => 'Campaña QA',
            'is_active' => true,
        ]);

        CampaignUserAssignment::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('transcripts.create'))
            ->assertOk();

        $supervisorsByCampaign = $response->viewData('supervisorsByCampaign');
        $agentsByCampaign = $response->viewData('agentsByCampaign');

        $this->assertArrayHasKey($campaign->id, $supervisorsByCampaign);
        $this->assertCount(1, $supervisorsByCampaign[$campaign->id]);
        $this->assertSame('SuperName SuperPat SuperMat', $supervisorsByCampaign[$campaign->id]->first()['name']);
        $this->assertSame($supervisor->id, $supervisorsByCampaign[$campaign->id]->first()['id']);

        $this->assertArrayHasKey($campaign->id, $agentsByCampaign);
        $this->assertCount(1, $agentsByCampaign[$campaign->id]);
        $this->assertSame('AgentName Paternal Maternal', $agentsByCampaign[$campaign->id]->first()['name']);
        $this->assertSame($agent->id, $agentsByCampaign[$campaign->id]->first()['id']);
        $this->assertSame($supervisor->id, $agentsByCampaign[$campaign->id]->first()['supervisor_id']);
    }

    private function userWithRoleAndPermissions(string $role, array $permissions = []): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        foreach ($permissions as $permission) {
            $roleModel->givePermissionTo(
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'])
            );
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiTextResponse(string $text): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisPayload(): array
    {
        return [
            'sentiment' => [
                'overall' => 'mixto',
                'overall_score' => -0.15,
                'agent' => [
                    'sentiment' => 'neutro',
                    'score' => 0.1,
                    'tone' => 'Cordial, pero con poca contención emocional',
                    'pace' => 'normal',
                    'energy' => 'media',
                ],
                'client' => [
                    'sentiment' => 'negativo',
                    'score' => -0.45,
                    'tone' => 'Molesto por el reclamo',
                    'pace' => 'rápido',
                    'energy' => 'alta',
                    'satisfaction' => 'insatisfecho',
                ],
                'summary' => 'El cliente expresa molestia y el agente necesita contener mejor el reclamo.',
            ],
            'sentiment_segments' => [
                [
                    'index' => 0,
                    'start' => 0,
                    'speaker' => 'agent',
                    'sentiment' => 'neutro',
                    'emotion' => 'calma',
                    'score' => 0.1,
                    'intensity' => 35,
                    'tone' => 'cordial',
                    'pace' => 'normal',
                    'volume' => 'medio',
                    'clarity' => 'claro',
                    'evidence' => 'saludo inicial',
                ],
                [
                    'index' => 1,
                    'start' => 4,
                    'speaker' => 'client',
                    'sentiment' => 'negativo',
                    'emotion' => 'molestia',
                    'score' => -0.45,
                    'intensity' => 70,
                    'tone' => 'molesto',
                    'pace' => 'rápido',
                    'volume' => 'alto',
                    'clarity' => 'claro',
                    'evidence' => 'Tengo un reclamo',
                ],
            ],
            'acoustic_analysis' => [
                'agent_speech_rate_wpm' => 110,
                'client_speech_rate_wpm' => 150,
                'overall_pace' => 'variable',
                'agent_energy' => 'media',
                'client_energy' => 'alta',
                'clarity' => 'claro',
                'interruptions' => 0,
                'agent_interruptions' => 0,
                'client_interruptions' => 0,
                'long_pauses' => 0,
                'silence_ratio' => 0.0,
                'talk_balance' => 'balanced',
                'talk_balance_note' => 'El cliente marca el reclamo y el agente abre la atención.',
                'emotional_turning_point' => [
                    'second' => 4,
                    'label' => '00:04',
                    'type' => 'deterioro',
                    'summary' => 'El cliente introduce una molestia.',
                ],
                'notes' => 'Ritmo variable por reclamo inicial.',
            ],
            'quality_signals' => [
                'empathy' => 'riesgo',
                'active_listening' => 'neutral',
                'objection_handling' => 'riesgo',
                'resolution_clarity' => 'neutral',
                'script_control' => 'neutral',
                'closing_quality' => 'neutral',
                'customer_experience_risk' => 'medio',
                'emotional_recovery' => 'no_detectado',
                'agent_control' => 'medio',
                'frustration_cause' => 'reclamo no detallado',
                'customer_left_unresolved' => true,
                'supervisor_alerts' => [],
                'critical_moments' => [],
                'coaching_recommendations' => [],
                'summary' => 'Se requiere validar emoción del cliente y aclarar el siguiente paso.',
            ],
        ];
    }

    private function wavBytes(int $durationSeconds): string
    {
        $sampleRate = 8000;
        $sampleCount = $sampleRate * $durationSeconds;
        $bitsPerSample = 16;
        $channels = 1;
        $bytesPerSample = (int) ($bitsPerSample / 8);
        $byteRate = $sampleRate * $channels * $bytesPerSample;
        $blockAlign = $channels * $bytesPerSample;
        $data = str_repeat(pack('v', 0), $sampleCount);
        $dataSize = strlen($data);

        return 'RIFF'
            .pack('V', 36 + $dataSize)
            .'WAVE'
            .'fmt '
            .pack('VvvVVvv', 16, 1, $channels, $sampleRate, $byteRate, $blockAlign, $bitsPerSample)
            .'data'
            .pack('V', $dataSize)
            .$data;
    }

    private function wavBytesWithToneAndSilence(): string
    {
        $sampleRate = 8000;
        $tone = $this->pcmTone($sampleRate, 1);
        $silence = str_repeat(pack('v', 0), $sampleRate * 3);

        return $this->wavBytesFromPcm($tone.$silence.$tone, $sampleRate);
    }

    private function pcmTone(int $sampleRate, int $durationSeconds): string
    {
        $data = '';
        $sampleCount = $sampleRate * $durationSeconds;

        for ($index = 0; $index < $sampleCount; $index++) {
            $sample = (int) round(sin(2 * pi() * 440 * ($index / $sampleRate)) * 12000);
            $data .= pack('v', $sample & 0xffff);
        }

        return $data;
    }

    private function wavBytesFromPcm(string $data, int $sampleRate): string
    {
        $bitsPerSample = 16;
        $channels = 1;
        $bytesPerSample = (int) ($bitsPerSample / 8);
        $byteRate = $sampleRate * $channels * $bytesPerSample;
        $blockAlign = $channels * $bytesPerSample;
        $dataSize = strlen($data);

        return 'RIFF'
            .pack('V', 36 + $dataSize)
            .'WAVE'
            .'fmt '
            .pack('VvvVVvv', 16, 1, $channels, $sampleRate, $byteRate, $blockAlign, $bitsPerSample)
            .'data'
            .pack('V', $dataSize)
            .$data;
    }

    private function compressedWavBytesWithFactDuration(int $factDurationSeconds, ?int $dataDurationSeconds = null): string
    {
        $sampleRate = 8000;
        $dataDurationSeconds ??= $factDurationSeconds;
        $factSampleCount = $sampleRate * $factDurationSeconds;
        $data = str_repeat("\xD5", $sampleRate * $dataDurationSeconds);
        $dataSize = strlen($data);
        $fmtChunk = 'fmt '
            .pack('VvvVVvvv', 18, 6, 1, $sampleRate, $sampleRate, 1, 8, 0);
        $factChunk = 'fact'.pack('VV', 4, $factSampleCount);

        return 'RIFF'
            .pack('V', 4 + strlen($fmtChunk) + strlen($factChunk) + 8 + $dataSize)
            .'WAVE'
            .$fmtChunk
            .$factChunk
            .'data'
            .pack('V', $dataSize)
            .$data;
    }
}
