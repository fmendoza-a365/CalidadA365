<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Interaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('transcripts.show', $interaction))
            ->assertOk()
            ->assertSee('Audio de la interacción')
            ->assertSee('Resumen emocional')
            ->assertSee('Lectura rápida')
            ->assertSee('Preocupación')
            ->assertSee('Cliente mejora al cierre.');
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
