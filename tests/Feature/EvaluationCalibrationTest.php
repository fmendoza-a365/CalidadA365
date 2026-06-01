<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityAttribute;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\QualitySubAttribute;
use App\Models\User;
use App\Services\EvaluationCalibrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EvaluationCalibrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calibration_service_compares_ai_and_manual_evaluation_pair(): void
    {
        [$admin] = $this->evaluationPair();

        $summary = app(EvaluationCalibrationService::class)->summary([
            'start_date' => now()->subDay()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
        ], $admin);

        $this->assertSame(1, $summary['pair_count']);
        $this->assertSame(70.0, $summary['average_ai_score']);
        $this->assertSame(80.0, $summary['average_manual_score']);
        $this->assertSame(10.0, $summary['average_score_delta']);
        $this->assertSame(10.0, $summary['average_absolute_delta']);
        $this->assertSame(50.0, $summary['item_agreement_rate']);
        $this->assertSame('IA más estricta', $summary['direction_label']);
    }

    public function test_internal_user_sees_calibration_card_on_evaluation_show(): void
    {
        [$admin, , $manualEvaluation] = $this->evaluationPair();

        $this->actingAs($admin)
            ->get(route('evaluations.show', $manualEvaluation))
            ->assertOk()
            ->assertSee('Calibración IA vs Monitor')
            ->assertSee('+10.0 pp')
            ->assertSee('50.0%')
            ->assertSee('Evidencia:')
            ->assertSee('Cliente confirma que recibió la solución.')
            ->assertSee('Nota IA original')
            ->assertSee('La IA no detectó cierre explícito.');
    }

    public function test_agent_does_not_see_internal_calibration_card(): void
    {
        [, $agent, $manualEvaluation] = $this->evaluationPair();

        $this->actingAs($agent)
            ->get(route('evaluations.show', $manualEvaluation))
            ->assertOk()
            ->assertDontSee('Calibración IA vs Monitor');
    }

    public function test_quality_dashboard_shows_calibration_summary(): void
    {
        [$admin] = $this->evaluationPair();

        $this->actingAs($admin)
            ->get(route('dashboard.quality'))
            ->assertOk()
            ->assertSee('Calibración IA')
            ->assertSee('Pares Comparados')
            ->assertSee('IA más estricta');
    }

    private function evaluationPair(): array
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
        $attribute = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Atención',
            'weight' => 100,
        ]);
        $greeting = QualitySubAttribute::create([
            'attribute_id' => $attribute->id,
            'name' => 'Saluda correctamente',
            'weight_percent' => 50,
        ]);
        $closing = QualitySubAttribute::create([
            'attribute_id' => $attribute->id,
            'name' => 'Cierra correctamente',
            'weight_percent' => 50,
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

        $aiEvaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'type' => 'ai',
            'total_score' => 70,
            'max_possible_score' => 100,
            'percentage_score' => 70,
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
        ]);
        $manualEvaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'type' => 'manual',
            'evaluator_id' => $admin->id,
            'total_score' => 80,
            'max_possible_score' => 100,
            'percentage_score' => 80,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'visible_to_agent_at' => now(),
        ]);

        $aiEvaluation->items()->createMany([
            [
                'subattribute_id' => $greeting->id,
                'status' => 'compliant',
                'score' => 1,
                'max_score' => 1,
                'weighted_score' => 50,
                'confidence' => 0.9,
            ],
            [
                'subattribute_id' => $closing->id,
                'status' => 'non_compliant',
                'score' => 0,
                'max_score' => 1,
                'weighted_score' => 0,
                'confidence' => 0.9,
                'evidence_quote' => 'Cliente confirma que recibió la solución.',
                'ai_notes' => 'La IA no detectó cierre explícito.',
            ],
        ]);
        $manualEvaluation->items()->createMany([
            [
                'subattribute_id' => $greeting->id,
                'status' => 'compliant',
                'score' => 1,
                'max_score' => 1,
                'weighted_score' => 50,
                'confidence' => 1,
            ],
            [
                'subattribute_id' => $closing->id,
                'status' => 'compliant',
                'score' => 1,
                'max_score' => 1,
                'weighted_score' => 50,
                'confidence' => 1,
                'ai_notes' => 'Monitor valida cierre por confirmación del cliente.',
            ],
        ]);

        return [$admin, $agent, $manualEvaluation, $aiEvaluation];
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
