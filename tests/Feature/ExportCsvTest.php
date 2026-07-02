<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\EvaluationItem;
use App\Models\Interaction;
use App\Models\QualityAttribute;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\QualitySubAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class ExportCsvTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    public function test_internal_user_can_export_evaluations_xlsx_with_quality_item_columns(): void
    {
        [$admin, , $campaign] = $this->evaluation();

        $response = $this->actingAs($admin)->get(route('exports.evaluations', ['campaign_id' => $campaign->id]));

        $response->assertOk();
        $this->assertStringContainsString('evaluaciones-falabella-retencion-', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'qa365-export-');
        file_put_contents($path, $response->streamedContent());

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $headers = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0];
        $row = $sheet->rangeToArray('A2:'.$sheet->getHighestColumn().'2')[0];

        $this->assertContains('campana', $headers);
        $this->assertContains('subcampana', $headers);
        $this->assertContains('link_audio', $headers);
        $this->assertNotContains('respuesta_ia_raw', $headers);
        $this->assertNotContains('items_detalle_json', $headers);
        $this->assertNotContains('metadata_json', $headers);
        $this->assertNotContains('Guion Renuncia', $headers);
        $this->assertSame('Falabella', $row[array_search('campana', $headers, true)]);
        $this->assertSame('Retencion', $row[array_search('subcampana', $headers, true)]);
        $this->assertStringContainsString('/transcripts/', $row[array_search('link_audio', $headers, true)]);
        $this->assertSame('Label: cordial', $row[array_search('tono_agente', $headers, true)]);
        $this->assertSame('1', $row[array_search('Saludo', $headers, true)]);
        $this->assertSame('0', $row[array_search('Validacion de identidad', $headers, true)]);
        $this->assertSame('NA', $row[array_search('Cierre', $headers, true)]);

        $spreadsheet->disconnectWorksheets();
        unlink($path);
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
        $parentCampaign = Campaign::create(['name' => 'Falabella']);
        $campaign = Campaign::create([
            'parent_id' => $parentCampaign->id,
            'name' => 'Retencion',
        ]);
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
            'file_path' => 'audio/test.mp3',
            'file_name' => 'test.mp3',
            'source_type' => 'audio',
            'transcript_text' => 'Test transcript',
            'status' => 'uploaded',
            'metadata' => [
                'sentiment' => [
                    'agent' => [
                        'tone' => ['label' => 'cordial'],
                    ],
                ],
            ],
        ]);
        $attribute = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Apertura',
            'weight' => 100,
            'sort_order' => 1,
        ]);
        $saludo = QualitySubAttribute::create([
            'attribute_id' => $attribute->id,
            'name' => 'Saludo',
            'weight_percent' => 33.33,
            'sort_order' => 1,
        ]);
        $validacion = QualitySubAttribute::create([
            'attribute_id' => $attribute->id,
            'name' => 'Validacion de identidad',
            'weight_percent' => 33.33,
            'sort_order' => 2,
        ]);
        $cierre = QualitySubAttribute::create([
            'attribute_id' => $attribute->id,
            'name' => 'Cierre',
            'weight_percent' => 33.34,
            'sort_order' => 3,
        ]);
        $evaluation = Evaluation::create([
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
        EvaluationItem::create([
            'evaluation_id' => $evaluation->id,
            'subattribute_id' => $saludo->id,
            'status' => 'compliant',
            'score' => 1,
            'max_score' => 1,
            'weighted_score' => 33.33,
        ]);
        EvaluationItem::create([
            'evaluation_id' => $evaluation->id,
            'subattribute_id' => $validacion->id,
            'status' => 'non_compliant',
            'score' => 0,
            'max_score' => 1,
            'weighted_score' => 0,
        ]);
        EvaluationItem::create([
            'evaluation_id' => $evaluation->id,
            'subattribute_id' => $cierre->id,
            'status' => 'not_found',
            'score' => 0,
            'max_score' => 1,
            'weighted_score' => 0,
        ]);

        $otherCampaign = Campaign::create([
            'parent_id' => $parentCampaign->id,
            'name' => 'Renuncia',
        ]);
        $otherForm = QualityForm::create([
            'campaign_id' => $otherCampaign->id,
            'name' => 'Renuncia Form',
            'created_by' => $admin->id,
        ]);
        $otherVersion = QualityFormVersion::create([
            'quality_form_id' => $otherForm->id,
            'version_number' => 1,
            'status' => 'published',
        ]);
        $otherAttribute = QualityAttribute::create([
            'form_version_id' => $otherVersion->id,
            'name' => 'Renuncia',
            'weight' => 100,
            'sort_order' => 1,
        ]);
        $otherItem = QualitySubAttribute::create([
            'attribute_id' => $otherAttribute->id,
            'name' => 'Guion Renuncia',
            'weight_percent' => 100,
            'sort_order' => 1,
        ]);
        $otherInteraction = Interaction::create([
            'campaign_id' => $otherCampaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'audio/renuncia.mp3',
            'file_name' => 'renuncia.mp3',
            'source_type' => 'audio',
            'transcript_text' => 'Other transcript',
            'status' => 'uploaded',
        ]);
        $otherEvaluation = Evaluation::create([
            'interaction_id' => $otherInteraction->id,
            'form_version_id' => $otherVersion->id,
            'campaign_id' => $otherCampaign->id,
            'agent_id' => $agent->id,
            'type' => 'manual',
            'evaluator_id' => $admin->id,
            'total_score' => 100,
            'max_possible_score' => 100,
            'percentage_score' => 100,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'visible_to_agent_at' => now(),
        ]);
        EvaluationItem::create([
            'evaluation_id' => $otherEvaluation->id,
            'subattribute_id' => $otherItem->id,
            'status' => 'compliant',
            'score' => 1,
            'max_score' => 1,
            'weighted_score' => 100,
        ]);

        return [$admin, $agent, $campaign];
    }
}
