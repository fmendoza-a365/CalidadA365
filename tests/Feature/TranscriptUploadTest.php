<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class TranscriptUploadTest extends TestCase
{
    use RefreshDatabase, CreatesUsersWithRoles;

    public function test_admin_can_upload_text_transcript(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $supervisor = $this->userWithRole('supervisor');
        $campaign = Campaign::create(['name' => 'Test Campaign', 'status' => 'active']);

        // Create assignment so the upload is authorized
        \App\Models\CampaignUserAssignment::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->createWithContent('transcript.txt', 'Agente: Buenos dias. Cliente: Gracias.');

        $this->actingAs($admin)
            ->post(route('transcripts.store'), [
                'campaign_id' => $campaign->id,
                'agent_id' => $agent->id,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'transcript_files' => [$file],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('interactions', [
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'uploaded_by' => $admin->id,
            'source_type' => 'text',
            'status' => 'uploaded',
        ]);
    }

    public function test_upload_requires_authentication(): void
    {
        $this->post(route('transcripts.store'), [])
            ->assertRedirect(route('login'));
    }

    public function test_agent_cannot_upload_transcripts(): void
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->post(route('transcripts.store'), [])
            ->assertForbidden();
    }

    public function test_upload_validates_required_fields(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('transcripts.store'), [])
            ->assertSessionHasErrors(['campaign_id', 'agent_id', 'occurred_at', 'transcript_files']);
    }

    public function test_upload_rejects_invalid_campaign(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');

        $file = UploadedFile::fake()->createWithContent('transcript.txt', 'Test content');

        $this->actingAs($admin)
            ->post(route('transcripts.store'), [
                'campaign_id' => 9999,
                'agent_id' => $agent->id,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'transcript_files' => [$file],
            ])
            ->assertSessionHasErrors(['campaign_id']);
    }

    public function test_admin_can_update_transcript(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $supervisor = $this->userWithRole('supervisor');
        $campaign = Campaign::create(['name' => 'Campaign', 'status' => 'active']);

        \App\Models\CampaignUserAssignment::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
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
            'transcript_text' => 'Original text',
            'status' => 'uploaded',
        ]);

        $this->actingAs($admin)
            ->put(route('transcripts.update', $interaction), [
                'campaign_id' => $campaign->id,
                'agent_id' => $agent->id,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'transcript_text' => 'Updated text',
                'contact_reason' => 'Consulta de producto',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('interactions', [
            'id' => $interaction->id,
            'contact_reason' => 'Consulta de producto',
        ]);
    }

    public function test_admin_can_delete_transcript(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $supervisor = $this->userWithRole('supervisor');
        $campaign = Campaign::create(['name' => 'Campaign', 'status' => 'active']);

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'transcripts/test.txt',
            'file_name' => 'test.txt',
            'source_type' => 'text',
            'transcript_text' => 'To be deleted',
            'status' => 'uploaded',
        ]);

        $this->actingAs($admin)
            ->delete(route('transcripts.destroy', $interaction))
            ->assertRedirect();

        $this->assertDatabaseMissing('interactions', ['id' => $interaction->id]);
    }

    public function test_agent_cannot_delete_transcripts(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $supervisor = $this->userWithRole('supervisor');
        $campaign = \App\Models\Campaign::create(['name' => 'Campaign', 'is_active' => true, 'type' => 'inbound']);

        $interaction = \App\Models\Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'transcripts/test.txt',
            'file_name' => 'test.txt',
            'source_type' => 'text',
            'transcript_text' => 'Test',
            'status' => 'uploaded',
        ]);

        $this->actingAs($agent)
            ->delete(route('transcripts.destroy', $interaction))
            ->assertForbidden();
    }
}
