<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\Interaction;
use App\Models\InsightReport;
use App\Models\QualityAttribute;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\QualitySubAttribute;
use App\Models\User;
use App\Services\AIEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InsightsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_only_sees_insight_reports_for_managed_campaigns(): void
    {
        $manager = $this->userWithRoleAndPermissions('manager', ['view_insights']);
        $creator = User::factory()->create();
        $managedCampaign = Campaign::create(['name' => 'Managed Campaign']);
        $otherCampaign = Campaign::create(['name' => 'Other Campaign']);

        $manager->managedCampaigns()->attach($managedCampaign);

        $this->report($managedCampaign, $creator);
        $this->report($otherCampaign, $creator);

        $response = $this->actingAs($manager)->get(route('insights.index'));

        $response->assertOk();
        $response->assertSee('Managed Campaign');
        $response->assertDontSee('Other Campaign');
    }

    public function test_manager_cannot_view_insight_report_for_unmanaged_campaign(): void
    {
        $manager = $this->userWithRoleAndPermissions('manager', ['view_insights']);
        $creator = User::factory()->create();
        $otherCampaign = Campaign::create(['name' => 'Other Campaign']);
        $report = $this->report($otherCampaign, $creator);

        $response = $this->actingAs($manager)->get(route('insights.show', $report));

        $response->assertForbidden();
    }

    public function test_manager_cannot_generate_insights_for_unmanaged_campaign(): void
    {
        $manager = $this->userWithRoleAndPermissions('manager', ['view_insights', 'generate_insights']);
        $otherCampaign = Campaign::create(['name' => 'Other Campaign']);

        $response = $this->actingAs($manager)->post(route('insights.generate'), [
            'campaign_id' => $otherCampaign->id,
            'type' => 'operational',
            'days' => 30,
        ]);

        $response->assertForbidden();
    }

    public function test_generated_report_contains_real_evaluation_snapshot(): void
    {
        $admin = $this->userWithRoleAndPermissions('admin', ['view_insights', 'generate_insights']);
        $agent = $this->userWithRoleAndPermissions('agent', []);
        $supervisor = $this->userWithRoleAndPermissions('supervisor', []);
        $campaign = Campaign::create(['name' => 'Atención Premium']);
        $form = QualityForm::create([
            'campaign_id' => $campaign->id,
            'name' => 'Ficha Premium',
            'created_by' => $admin->id,
        ]);
        $version = QualityFormVersion::create([
            'quality_form_id' => $form->id,
            'version_number' => 1,
            'status' => 'published',
        ]);
        $attribute = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Protocolo',
            'weight' => 100,
        ]);
        $subAttribute = QualitySubAttribute::create([
            'attribute_id' => $attribute->id,
            'name' => 'Saluda correctamente',
            'weight_percent' => 100,
            'is_critical' => true,
        ]);

        foreach ([62, 74, 91] as $score) {
            $interaction = Interaction::create([
                'campaign_id' => $campaign->id,
                'agent_id' => $agent->id,
                'supervisor_id' => $supervisor->id,
                'occurred_at' => now()->subDays(3),
                'uploaded_by' => $admin->id,
                'file_path' => 'transcripts/insight-test.txt',
                'file_name' => 'insight-test.txt',
                'source_type' => 'text',
                'transcript_text' => 'Transcripción para reporte de insights.',
                'status' => 'uploaded',
            ]);

            $evaluation = Evaluation::create([
                'interaction_id' => $interaction->id,
                'form_version_id' => $version->id,
                'campaign_id' => $campaign->id,
                'agent_id' => $agent->id,
                'type' => 'manual',
                'evaluator_id' => $admin->id,
                'total_score' => $score,
                'max_possible_score' => 100,
                'percentage_score' => $score,
                'status' => Evaluation::STATUS_APPROVED,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]);

            EvaluationItem::create([
                'evaluation_id' => $evaluation->id,
                'subattribute_id' => $subAttribute->id,
                'status' => 'non_compliant',
                'score' => 0,
                'max_score' => 10,
                'weighted_score' => 0,
                'evidence_quote' => 'No realizó saludo de apertura.',
            ]);
        }

        $this->mock(AIEvaluationService::class, function ($mock) {
            $mock->shouldReceive('generateInsightReport')
                ->once()
                ->andReturnUsing(fn ($evaluations, $type, $snapshot) => [
                    'executive_summary' => 'Resumen ejecutivo generado desde evaluaciones.',
                    'operations_summary' => 'Resumen operativo generado.',
                    'client_summary' => 'Resumen para cliente generado.',
                    'recommendations' => [
                        [
                            'priority' => 1,
                            'action' => 'Reforzar saludo de apertura',
                            'expected_impact' => 'Mejorar consistencia',
                            'responsible' => 'Operaciones',
                        ],
                    ],
                    'presentation_slides' => [
                        [
                            'title' => 'Hallazgo principal',
                            'bullets' => ['Saludo de apertura requiere refuerzo'],
                            'speaker_note' => 'Usar evidencias reales.',
                        ],
                    ],
                ]);
        });

        $this->actingAs($admin)
            ->post(route('insights.generate'), [
                'campaign_id' => $campaign->id,
                'type' => 'operational',
                'days' => 30,
            ])
            ->assertRedirect();

        $report = InsightReport::firstOrFail();
        $snapshot = $report->key_findings['report_snapshot'];

        $this->assertSame(3, $snapshot['metrics']['total_evaluations']);
        $this->assertSame(75.7, $snapshot['metrics']['average_score']);
        $this->assertSame('Saluda correctamente', $snapshot['top_failed_criteria'][0]['criteria']);

        $this->actingAs($admin)
            ->get(route('insights.show', $report))
            ->assertOk()
            ->assertSee('Informe ejecutivo de calidad')
            ->assertSee('Resumen operativo generado')
            ->assertSee('Saluda correctamente')
            ->assertSee('Hallazgo principal');
    }

    public function test_manager_can_generate_insights_using_parent_campaign_id(): void
    {
        $admin = $this->userWithRoleAndPermissions('admin', ['view_insights', 'generate_insights']);
        $agent = $this->userWithRoleAndPermissions('agent', []);
        $supervisor = $this->userWithRoleAndPermissions('supervisor', []);
        
        $parentCampaign = Campaign::create(['name' => 'Parent Campaign']);
        $subCampaign = Campaign::create(['name' => 'Sub Campaign', 'parent_id' => $parentCampaign->id]);
        
        $form = QualityForm::create([
            'campaign_id' => $subCampaign->id,
            'name' => 'Ficha Premium',
            'created_by' => $admin->id,
        ]);
        $version = QualityFormVersion::create([
            'quality_form_id' => $form->id,
            'version_number' => 1,
            'status' => 'published',
        ]);
        
        $interaction = Interaction::create([
            'campaign_id' => $subCampaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => now()->subDays(3),
            'uploaded_by' => $admin->id,
            'file_path' => 'transcripts/insight-test.txt',
            'file_name' => 'insight-test.txt',
            'source_type' => 'text',
            'transcript_text' => 'Transcripción para reporte de insights.',
            'status' => 'uploaded',
        ]);

        $evaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $subCampaign->id,
            'agent_id' => $agent->id,
            'type' => 'manual',
            'evaluator_id' => $admin->id,
            'total_score' => 90,
            'max_possible_score' => 100,
            'percentage_score' => 90,
            'status' => Evaluation::STATUS_APPROVED,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $this->mock(AIEvaluationService::class, function ($mock) {
            $mock->shouldReceive('generateInsightReport')
                ->once()
                ->andReturn([
                    'executive_summary' => 'Resumen ejecutivo para parent.',
                ]);
        });

        $this->actingAs($admin)
            ->post(route('insights.generate'), [
                'parent_campaign_id' => $parentCampaign->id,
                'type' => 'operational',
                'days' => 30,
            ])
            ->assertRedirect();

        $report = InsightReport::firstOrFail();
        $this->assertSame($parentCampaign->id, $report->campaign_id);
    }

    private function userWithRoleAndPermissions(string $roleName, array $permissionNames): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function report(Campaign $campaign, User $creator): InsightReport
    {
        return InsightReport::create([
            'campaign_id' => $campaign->id,
            'type' => 'operational',
            'date_range_start' => now()->subDays(30)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'summary_content' => 'Summary',
            'key_findings' => ['executive_summary' => 'Summary'],
            'generated_by' => $creator->id,
        ]);
    }
}
