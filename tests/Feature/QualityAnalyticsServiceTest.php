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
use App\Models\User;
use App\Services\QualityAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_dashboard_groups_by_week_of_selected_month(): void
    {
        [$campaign, $agent, $supervisor, $version, $criticalSubAttribute] = $this->dashboardFixtures();

        $this->createEvaluation($campaign, $agent, $supervisor, $version, $criticalSubAttribute, '2026-06-02 10:00:00', 80, true);
        $this->createEvaluation($campaign, $agent, $supervisor, $version, $criticalSubAttribute, '2026-06-10 10:00:00', 60, false);
        $this->createEvaluation($campaign, $agent, $supervisor, $version, $criticalSubAttribute, '2026-06-24 10:00:00', 90, true);

        $filters = [
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ];

        $analytics = app(QualityAnalyticsService::class);

        $this->assertSame(
            ['Semana 1', 'Semana 2', 'Semana 4'],
            array_column($analytics->getQualityGrouped('week', $filters), 'label')
        );

        $this->assertSame(
            ['Semana 1', 'Semana 2', 'Semana 4'],
            array_column($analytics->getMPGrouped('week', $filters), 'label')
        );

        $this->assertSame(
            ['Semana 1', 'Semana 2', 'Semana 4'],
            array_column($analytics->getFeedbackByWeek($filters), 'label')
        );
    }

    private function dashboardFixtures(): array
    {
        $admin = User::factory()->create();
        $agent = User::factory()->create();
        $supervisor = User::factory()->create();
        $campaign = Campaign::create(['name' => 'Claro']);
        $form = QualityForm::create([
            'campaign_id' => $campaign->id,
            'name' => 'Ficha Claro',
            'created_by' => $admin->id,
        ]);
        $version = QualityFormVersion::create([
            'quality_form_id' => $form->id,
            'version_number' => 1,
            'status' => 'published',
        ]);
        $attribute = QualityAttribute::create([
            'form_version_id' => $version->id,
            'name' => 'Venta',
            'weight' => 100,
        ]);
        $criticalSubAttribute = QualitySubAttribute::create([
            'attribute_id' => $attribute->id,
            'name' => 'Consentimiento',
            'weight_percent' => 100,
            'is_critical' => true,
        ]);

        return [$campaign, $agent, $supervisor, $version, $criticalSubAttribute];
    }

    private function createEvaluation(
        Campaign $campaign,
        User $agent,
        User $supervisor,
        QualityFormVersion $version,
        QualitySubAttribute $criticalSubAttribute,
        string $createdAt,
        int $score,
        bool $feedbackDone
    ): Evaluation {
        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => $createdAt,
            'uploaded_by' => $supervisor->id,
            'file_path' => 'transcripts/test.txt',
            'file_name' => 'test.txt',
            'transcript_text' => 'Agente: saludo. Cliente: respuesta.',
            'status' => 'uploaded',
        ]);
        $interaction->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        $evaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'type' => 'ai',
            'total_score' => $score,
            'max_possible_score' => 100,
            'percentage_score' => $score,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'agent_viewed_at' => $feedbackDone ? $createdAt : null,
        ]);
        $evaluation->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        EvaluationItem::create([
            'evaluation_id' => $evaluation->id,
            'subattribute_id' => $criticalSubAttribute->id,
            'status' => 'non_compliant',
            'score' => 0,
            'max_score' => 100,
            'weighted_score' => 0,
        ]);

        return $evaluation;
    }
}
