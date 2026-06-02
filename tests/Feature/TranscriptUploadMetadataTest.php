<?php

namespace Tests\Feature;

use App\Jobs\TranscribeAudioJob;
use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\Interaction;
use App\Models\User;
use App\Services\AudioTranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    private function compressedWavBytesWithFactDuration(int $durationSeconds): string
    {
        $sampleRate = 8000;
        $factSampleCount = $sampleRate * $durationSeconds;
        $data = str_repeat("\xD5", $sampleRate);
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
