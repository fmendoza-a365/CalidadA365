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
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_user_can_login_and_read_quality_dashboard_data(): void
    {
        [$admin, $evaluation] = $this->criticalEvaluation();

        $token = $this->postJson('/api/mobile/login', [
            'login' => $admin->email,
            'password' => 'password',
            'device_name' => 'Feature test',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', $admin->email)
            ->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/summary')
            ->assertOk()
            ->assertJsonPath('summary.total_evaluations', 1)
            ->assertJsonPath('summary.average_score', 55)
            ->assertJsonPath('summary.critical_scores', 1);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/alerts')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Revisar evaluacion de calidad'])
            ->assertJsonFragment(['title' => 'Tiempo muerto relevante'])
            ->assertJsonFragment(['evaluation_id' => $evaluation->id]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/evaluations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $evaluation->id)
            ->assertJsonPath('data.0.score', 55)
            ->assertJsonPath('data.0.feedback_indicators.customer_experience_risk', 'Alto')
            ->assertJsonPath('data.0.audio.dead_air_seconds', 45);
    }

    public function test_mobile_api_rejects_invalid_tokens(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/mobile/summary')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Token movil invalido o vencido.');
    }

    public function test_mobile_dashboard_returns_executive_payload(): void
    {
        [$admin] = $this->criticalEvaluation();

        $token = $this->postJson('/api/mobile/login', [
            'login' => $admin->email,
            'password' => 'password',
        ])->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('profile.primary_view', 'executive')
            ->assertJsonPath('overview.total_evaluations', 1)
            ->assertJsonPath('summary.critical_scores', 1)
            ->assertJsonPath('modules.transcripts.summary.total', 1)
            ->assertJsonPath('modules.campaigns.summary.total', 1)
            ->assertJsonPath('modules.quality_forms.summary.total', 1)
            ->assertJsonPath('modules.evaluations.summary.pending_monitor', 1)
            ->assertJsonPath('ranking.0.total_evals', 1)
            ->assertJsonCount(1, 'evaluations');
    }

    public function test_mobile_dashboard_returns_agent_payload_for_advisors(): void
    {
        [, $evaluation] = $this->criticalEvaluation();
        $agent = $evaluation->agent;
        $evaluation->forceFill([
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'visible_to_agent_at' => now(),
        ])->save();

        $token = $this->postJson('/api/mobile/login', [
            'login' => $agent->email,
            'password' => 'password',
        ])->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('profile.primary_view', 'agent')
            ->assertJsonPath('overview.total_evaluations', 1)
            ->assertJsonPath('modules.feedback.summary.published', 1)
            ->assertJsonPath('modules.feedback.summary.pending_response', 1)
            ->assertJsonPath('agent.league.name', 'Hierro')
            ->assertJsonPath('agent.match_history.0.id', $evaluation->id);
    }

    public function test_mobile_logout_revokes_current_token(): void
    {
        [$admin] = $this->criticalEvaluation();

        $token = $this->postJson('/api/mobile/login', [
            'login' => $admin->username,
            'password' => 'password',
        ])->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/mobile/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesion movil cerrada.');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/summary')
            ->assertUnauthorized();
    }

    private function criticalEvaluation(): array
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');
        $supervisor = $this->userWithRole('supervisor');
        $campaign = Campaign::create(['name' => 'Claro']);
        $form = QualityForm::create([
            'campaign_id' => $campaign->id,
            'name' => 'Ficha QA',
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
            'file_path' => 'audio/test.wav',
            'file_name' => 'test.wav',
            'source_type' => 'audio',
            'audio_duration' => 180,
            'transcript_text' => 'Cliente reporta problemas sin resolver.',
            'status' => 'uploaded',
            'metadata' => [
                'quality_signals' => [
                    'empathy' => 'Riesgo',
                    'active_listening' => 'No detectado',
                    'customer_left_unresolved' => true,
                    'customer_experience_risk' => 'Alto',
                ],
                'acoustic_analysis' => [
                    'dead_air_total_seconds' => 45,
                    'dead_air_total_label' => '00:45',
                    'silence_ratio' => 0.25,
                ],
            ],
        ]);

        $evaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'type' => 'ai',
            'total_score' => 55,
            'max_possible_score' => 100,
            'percentage_score' => 55,
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            'ai_summary' => 'La llamada requiere revision por riesgo de experiencia.',
        ]);

        return [$admin, $evaluation];
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
