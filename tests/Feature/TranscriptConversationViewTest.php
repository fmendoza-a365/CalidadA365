<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TranscriptConversationViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_transcripts_are_rendered_as_conversation_turns(): void
    {
        $admin = $this->userWithRole('admin');
        $campaign = Campaign::create(['name' => 'Soporte']);
        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $admin->id,
            'supervisor_id' => $admin->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'transcripts/demo.txt',
            'file_name' => 'demo.txt',
            'source_type' => 'text',
            'transcript_text' => 'Agente: Buenos dias. Cliente: Gracias por la ayuda.',
            'status' => 'uploaded',
        ]);

        $this->actingAs($admin)
            ->get(route('transcripts.show', $interaction))
            ->assertOk()
            ->assertSee('Conversación')
            ->assertSee('Agente')
            ->assertSee('Cliente')
            ->assertSee('Buenos dias.')
            ->assertSee('Gracias por la ayuda.')
            ->assertSee('Ver texto plano');
    }

    public function test_audio_transcripts_render_custom_waveform_and_emotional_summary(): void
    {
        $admin = $this->userWithRole('admin');
        $campaign = Campaign::create(['name' => 'Soporte']);
        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $admin->id,
            'supervisor_id' => $admin->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'demo/audio.wav',
            'file_name' => 'audio.wav',
            'source_type' => 'audio',
            'audio_duration' => 30,
            'transcription_status' => 'completed',
            'transcript_text' => "[00:00] Agente: Hola\n[00:08] Cliente: Estoy preocupado\n[00:16] Agente: Lo resolvemos ahora",
            'status' => 'scored',
            'metadata' => [
                'sentiment' => [
                    'overall' => 'positivo',
                    'summary' => 'Cliente mejora al cierre.',
                    'agent' => ['score' => 0.7, 'tone' => 'Calmo.'],
                    'client' => ['score' => 0.2, 'tone' => 'Preocupado al inicio.'],
                ],
                'sentiment_segments' => [
                    ['index' => 1, 'sentiment' => 'mixto', 'emotion' => 'preocupacion', 'score' => -0.2],
                ],
                'acoustic_analysis' => [
                    'overall_pace' => 'normal',
                    'talk_balance' => 'balanced',
                    'talk_balance_note' => 'Conversación balanceada.',
                    'interruptions' => 1,
                    'long_pauses' => 1,
                    'silence_ratio' => 0.1,
                    'dead_air_total_seconds' => 3.0,
                    'dead_air_total_label' => '00:03',
                    'dead_air_longest_seconds' => 3.0,
                    'dead_air_longest_label' => '00:12-00:15',
                    'dead_air_segments' => [
                        [
                            'start' => 12.0,
                            'end' => 15.0,
                            'duration' => 3.0,
                            'start_label' => '00:12',
                            'end_label' => '00:15',
                            'duration_label' => '00:03',
                        ],
                    ],
                ],
                'quality_signals' => [
                    'empathy' => 'fortaleza',
                    'active_listening' => 'fortaleza',
                    'objection_handling' => 'neutral',
                    'resolution_clarity' => 'riesgo',
                    'script_control' => 'neutral',
                    'closing_quality' => 'fortaleza',
                    'customer_experience_risk' => 'medio',
                    'emotional_recovery' => 'contiene',
                    'agent_control' => 'medio',
                    'frustration_cause' => 'duda del cliente',
                    'customer_left_unresolved' => true,
                    'critical_moments' => [
                        [
                            'label' => '00:08',
                            'type' => 'oportunidad',
                            'title' => 'Cliente preocupado',
                            'evidence' => 'Estoy preocupado',
                            'feedback' => 'Reforzar empatía antes de continuar.',
                        ],
                    ],
                    'coaching_recommendations' => [
                        [
                            'priority' => 'alta',
                            'skill' => 'empatía',
                            'recommendation' => 'Validar la preocupación del cliente.',
                            'example' => 'Entiendo su preocupación.',
                        ],
                    ],
                    'summary' => 'Hay señales útiles para feedback.',
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('transcripts.show', $interaction))
            ->assertOk()
            ->assertSee('Audio de la interacción')
            ->assertSee('Resumen emocional')
            ->assertSee('Lectura rápida')
            ->assertSee('Preocupación')
            ->assertSee('Señales de voz')
            ->assertSee('Indicadores de feedback')
            ->assertSee('Escucha activa')
            ->assertSee('Claridad solución')
            ->assertSee('Tiempo muerto')
            ->assertSee('00:12-00:15')
            ->assertSee('Momentos críticos')
            ->assertSee('Cliente preocupado')
            ->assertSee('Recomendaciones coaching')
            ->assertSee('Validar la preocupación del cliente.')
            ->assertSee('Línea de tiempo')
            ->assertSee('data-audio-timeline-track', false)
            ->assertSee('seekFromTimeline($event)', false)
            ->assertSee('eventSegments.length', false)
            ->assertSee('selectSegment(segment)', false)
            ->assertSee('selectTurn(0, \'turn-0\')', false)
            ->assertSee('Cliente mejora al cierre.');
    }

    public function test_audio_endpoint_supports_range_requests_for_browser_seek(): void
    {
        Storage::fake('local');

        $admin = $this->userWithRole('admin');
        $campaign = Campaign::create(['name' => 'Soporte']);
        $bytes = '0123456789abcdef';
        Storage::disk('local')->put('demo/range.wav', $bytes);

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $admin->id,
            'supervisor_id' => $admin->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'demo/range.wav',
            'file_name' => 'range.wav',
            'source_type' => 'audio',
            'audio_duration' => 10,
            'transcription_status' => 'completed',
            'transcript_text' => '[00:00] Agente: Hola',
            'status' => 'uploaded',
        ]);

        $response = $this->actingAs($admin)
            ->withHeaders(['Range' => 'bytes=2-5'])
            ->get(route('transcripts.audio', $interaction));

        $response
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 2-5/16')
            ->assertHeader('Content-Length', '4');

        $this->assertSame('2345', $response->streamedContent());

        $this->actingAs($admin)
            ->withHeaders(['Range' => 'bytes=99-120'])
            ->get(route('transcripts.audio', $interaction))
            ->assertStatus(416)
            ->assertHeader('Content-Range', 'bytes */16');
    }

    public function test_audio_interaction_player_is_visible_on_evaluation_show_for_transcript_viewers(): void
    {
        $admin = $this->userWithRole('admin');
        $campaign = Campaign::create(['name' => 'Soporte']);
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
            'agent_id' => $admin->id,
            'supervisor_id' => $admin->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'demo/audio.wav',
            'file_name' => 'audio.wav',
            'source_type' => 'audio',
            'audio_duration' => 30,
            'transcription_status' => 'completed',
            'transcript_text' => '[00:00] Agente: Hola',
            'status' => 'scored',
        ]);
        $evaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $admin->id,
            'type' => 'ai',
            'total_score' => 100,
            'max_possible_score' => 100,
            'percentage_score' => 100,
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            'ai_summary' => 'Feedback IA.',
        ]);

        $this->actingAs($admin)
            ->get(route('evaluations.show', $evaluation))
            ->assertOk()
            ->assertSee('Audio de la interacción')
            ->assertSee(route('transcripts.audio', $interaction), false);
    }

    private function userWithRole(string $role): User
    {
        $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'view_transcripts', 'guard_name' => 'web']);
        $roleModel->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
