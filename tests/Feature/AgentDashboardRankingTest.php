<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class AgentDashboardRankingTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    public function test_agent_team_ranking_is_paginated_by_ten_rows(): void
    {
        $admin = $this->userWithRole('admin');
        $supervisor = $this->userWithRole('supervisor');
        $campaign = Campaign::create(['name' => 'Campaña Ranking', 'type' => 'inbound', 'is_active' => true]);
        $version = $this->qualityFormVersion($campaign, $admin);
        $agents = collect();

        for ($i = 1; $i <= 12; $i++) {
            $agent = $this->userWithRole('agent', [
                'name' => sprintf('Ranking Agente %02d', $i),
            ]);

            CampaignUserAssignment::create([
                'campaign_id' => $campaign->id,
                'agent_id' => $agent->id,
                'supervisor_id' => $supervisor->id,
                'is_active' => true,
            ]);

            $this->evaluationFor($campaign, $version, $agent, $supervisor, $admin, 101 - $i);
            $agents->push($agent);
        }

        $this->actingAs($agents->first())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ranking Agente 10')
            ->assertDontSee('Ranking Agente 11')
            ->assertDontSee('Ranking Agente 12');

        $this->actingAs($agents->first())
            ->get(route('dashboard', ['ranking_page' => 2]))
            ->assertOk()
            ->assertSee('Ranking Agente 11')
            ->assertSee('Ranking Agente 12')
            ->assertDontSee('Ranking Agente 10');
    }

    private function qualityFormVersion(Campaign $campaign, User $admin): QualityFormVersion
    {
        $form = QualityForm::create([
            'campaign_id' => $campaign->id,
            'name' => 'Ficha Ranking',
            'created_by' => $admin->id,
        ]);

        return QualityFormVersion::create([
            'quality_form_id' => $form->id,
            'version_number' => 1,
            'status' => 'published',
            'is_active' => true,
            'published_at' => now(),
            'published_by' => $admin->id,
        ]);
    }

    private function evaluationFor(
        Campaign $campaign,
        QualityFormVersion $version,
        User $agent,
        User $supervisor,
        User $admin,
        int $score
    ): Evaluation {
        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => now(),
            'uploaded_by' => $admin->id,
            'file_path' => 'transcripts/ranking-'.$agent->id.'.txt',
            'file_name' => 'ranking-'.$agent->id.'.txt',
            'source_type' => 'text',
            'transcript_text' => 'Texto de prueba ranking',
            'status' => 'uploaded',
        ]);

        return Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'type' => 'manual',
            'evaluator_id' => $admin->id,
            'total_score' => $score,
            'max_possible_score' => 100,
            'percentage_score' => $score,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'visible_to_agent_at' => now(),
        ]);
    }
}
