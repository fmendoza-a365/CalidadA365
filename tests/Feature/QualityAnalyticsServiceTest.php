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

    public function test_evolution_series_include_volume_percentages_delta_and_insights(): void
    {
        [$campaign, $agent, $supervisor, $version, $criticalSubAttribute] = $this->dashboardFixtures();

        $this->createEvaluation($campaign, $agent, $supervisor, $version, $criticalSubAttribute, '2026-06-02 10:00:00', 80, true);
        $this->createEvaluation($campaign, $agent, $supervisor, $version, $criticalSubAttribute, '2026-06-02 11:00:00', 90, false, 'compliant');
        $this->createEvaluation($campaign, $agent, $supervisor, $version, $criticalSubAttribute, '2026-06-10 10:00:00', 70, false);

        $filters = [
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ];

        $analytics = app(QualityAnalyticsService::class);

        $quality = $analytics->getQualityTrendSeries($filters)['day'];
        $this->assertSame(2, $quality[0]['count']);
        $this->assertSame(85.0, $quality[0]['avg_score']);
        $this->assertNull($quality[0]['trend_delta']);
        $this->assertSame('Consentimiento', $quality[0]['top_defect']);
        $this->assertStringContainsString('Principal falla: Consentimiento', $quality[0]['insight']);
        $this->assertSame(-15.0, $quality[1]['trend_delta']);

        $mp = $analytics->getMpTrendSeries($filters)['day'];
        $this->assertSame(2, $mp[0]['total']);
        $this->assertSame(1, $mp[0]['count']);
        $this->assertSame(1, $mp[0]['evaluations_with_mp']);
        $this->assertSame(50.0, $mp[0]['percentage']);
        $this->assertSame(50.0, $mp[1]['trend_delta']);
        $this->assertStringContainsString('Principal MP: Consentimiento', $mp[0]['insight']);

        $feedback = $analytics->getFeedbackTrendSeries($filters)['day'];
        $this->assertSame(2, $feedback[0]['total']);
        $this->assertSame(1, $feedback[0]['done']);
        $this->assertSame(1, $feedback[0]['pending']);
        $this->assertSame(50.0, $feedback[0]['done_pct']);
        $this->assertSame(-50.0, $feedback[1]['trend_delta']);
        $this->assertStringContainsString('pendientes', $feedback[0]['insight']);
    }

    public function test_top_defects_can_be_limited_to_critical_malas_practicas(): void
    {
        [$campaign, $agent, $supervisor, $version, $criticalSubAttribute] = $this->dashboardFixtures();
        $nonCriticalSubAttribute = QualitySubAttribute::create([
            'attribute_id' => $criticalSubAttribute->attribute_id,
            'name' => 'Tono no empatico',
            'weight_percent' => 100,
            'is_critical' => false,
        ]);

        $this->createEvaluation($campaign, $agent, $supervisor, $version, $criticalSubAttribute, '2026-07-02 10:00:00', 60, true);
        $this->createEvaluation($campaign, $agent, $supervisor, $version, $nonCriticalSubAttribute, '2026-07-03 10:00:00', 80, true);
        $this->createEvaluation($campaign, $agent, $supervisor, $version, $nonCriticalSubAttribute, '2026-07-04 10:00:00', 82, true);

        $filters = [
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ];

        $analytics = app(QualityAnalyticsService::class);
        $criticalOnly = $analytics->getTopDefects($filters, 10, true);
        $allDefects = $analytics->getTopDefects($filters);

        $this->assertCount(1, $criticalOnly);
        $this->assertSame('Consentimiento', $criticalOnly[0]['label']);
        $this->assertSame(1, $criticalOnly[0]['count']);
        $this->assertTrue($criticalOnly[0]['is_critical']);

        $this->assertSame('Tono no empatico', $allDefects[0]['label']);
        $this->assertSame(2, $allDefects[0]['count']);
        $this->assertFalse($allDefects[0]['is_critical']);
    }

    public function test_manual_evaluation_replaces_ai_sibling_in_reporting_counts(): void
    {
        [$campaign, $agent, $supervisor, $version, $criticalSubAttribute] = $this->dashboardFixtures();

        $aiEvaluation = $this->createEvaluation(
            $campaign,
            $agent,
            $supervisor,
            $version,
            $criticalSubAttribute,
            '2026-07-08 10:00:00',
            55,
            false
        );

        $manualEvaluation = Evaluation::create([
            'interaction_id' => $aiEvaluation->interaction_id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'type' => 'manual',
            'total_score' => 92,
            'max_possible_score' => 100,
            'percentage_score' => 92,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'visible_to_agent_at' => '2026-07-08 10:30:00',
        ]);
        $manualEvaluation->forceFill([
            'created_at' => '2026-07-08 10:30:00',
            'updated_at' => '2026-07-08 10:30:00',
        ])->save();

        $filters = [
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ];

        $analytics = app(QualityAnalyticsService::class);
        $overview = $analytics->getOverviewStats($filters);
        $ranking = $analytics->getAgentRanking($filters);
        $qualityByAgent = $analytics->getQualityGrouped('agent', $filters);

        $this->assertSame(1, $overview['total_evaluations']);
        $this->assertSame(92.0, $overview['average_score']);
        $this->assertSame(1, $ranking[0]['total_evals']);
        $this->assertSame(92.0, $ranking[0]['avg_score']);
        $this->assertSame(1, $qualityByAgent[0]['count']);
        $this->assertSame(92.0, $qualityByAgent[0]['avg_score']);
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
        bool $feedbackDone,
        string $itemStatus = 'non_compliant'
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
            'status' => $itemStatus,
            'score' => 0,
            'max_score' => 100,
            'weighted_score' => 0,
        ]);

        return $evaluation;
    }
}
