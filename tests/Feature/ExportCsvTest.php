<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class ExportCsvTest extends TestCase
{
    use RefreshDatabase, CreatesUsersWithRoles;

    public function test_internal_user_can_export_evaluations_csv(): void
    {
        [$admin] = $this->evaluation();

        $response = $this->actingAs($admin)->get(route('exports.evaluations'));

        $response->assertOk();
        $this->assertStringContainsString('evaluations.csv', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('campaign', $response->streamedContent());
    }

    public function test_agent_cannot_export_calibration_csv(): void
    {
        [, $agent] = $this->evaluation();

        $this->actingAs($agent)
            ->get(route('exports.calibration'))
            ->assertForbidden();
    }

    private function evaluation(): array
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
            'type' => 'manual',
            'evaluator_id' => $admin->id,
            'total_score' => 90,
            'max_possible_score' => 100,
            'percentage_score' => 90,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'visible_to_agent_at' => now(),
        ]);

        return [$admin, $agent];
    }
}
