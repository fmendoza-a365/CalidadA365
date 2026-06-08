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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class EvaluationAuditTrailTest extends TestCase
{
    use RefreshDatabase, CreatesUsersWithRoles;

    public function test_publishing_evaluation_records_audit_event(): void
    {
        [$admin, $agent, $evaluation] = $this->evaluation(Evaluation::STATUS_PENDING_MONITOR_REVIEW);

        $this->actingAs($admin)
            ->post(route('evaluations.publish', $evaluation), [
                'review_notes' => 'Lista para publicar.',
            ])
            ->assertRedirect(route('evaluations.show', $evaluation));

        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_id' => $evaluation->id,
            'actor_id' => $admin->id,
            'event' => 'published',
            'from_status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            'to_status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
        ]);
        $this->assertSame(Evaluation::STATUS_PUBLISHED_TO_AGENT, $evaluation->fresh()->status);
    }

    public function test_agent_dispute_records_audit_event(): void
    {
        [, $agent, $evaluation] = $this->evaluation(Evaluation::STATUS_PUBLISHED_TO_AGENT);

        $this->actingAs($agent)
            ->post(route('evaluations.respond', $evaluation), [
                'response_type' => 'dispute',
                'dispute_reason' => 'La evidencia no corresponde al criterio.',
            ])
            ->assertRedirect(route('evaluations.show', $evaluation));

        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_id' => $evaluation->id,
            'actor_id' => $agent->id,
            'event' => 'agent_disputed',
            'from_status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
            'to_status' => Evaluation::STATUS_AGENT_DISPUTED,
        ]);
        $this->assertDatabaseHas('dispute_resolutions', [
            'evaluation_id' => $evaluation->id,
        ]);
    }

    public function test_manual_evaluation_records_audit_event(): void
    {
        [$admin, , , $version, $interaction, $subAttribute] = $this->scorableInteraction();

        $response = $this->actingAs($admin)
            ->post(route('evaluations.store_manual', $interaction), [
                'form_version_id' => $version->id,
                'items' => [
                    [
                        'subattribute_id' => $subAttribute->id,
                        'status' => 'compliant',
                        'notes' => 'Cumple el criterio.',
                    ],
                ],
            ]);

        $evaluation = Evaluation::where('interaction_id', $interaction->id)
            ->where('type', 'manual')
            ->firstOrFail();

        $response->assertRedirect(route('evaluations.show', $evaluation));
        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_id' => $evaluation->id,
            'actor_id' => $admin->id,
            'event' => 'manual_created',
            'from_status' => null,
            'to_status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
        ]);
    }

    public function test_assigned_monitor_can_create_manual_correction_without_campaign_assignment(): void
    {
        [, $agent, , $version, $interaction, $subAttribute] = $this->scorableInteraction();
        $monitor = $this->userWithRole('qa_monitor');

        $aiEvaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $agent->id,
            'type' => 'ai',
            'evaluator_id' => $monitor->id,
            'total_score' => 80,
            'max_possible_score' => 100,
            'percentage_score' => 80,
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            'ai_summary' => '### Feedback IA Base',
        ]);

        $aiEvaluation->items()->create([
            'subattribute_id' => $subAttribute->id,
            'status' => 'non_compliant',
            'score' => 0,
            'max_score' => 1,
            'weighted_score' => 0,
            'confidence' => 0.9,
            'evidence_quote' => 'El agente valida correctamente al cliente.',
            'ai_notes' => 'La IA marcó incumplimiento inicial.',
        ]);

        $this->actingAs($monitor)
            ->get(route('evaluations.create_manual', $interaction))
            ->assertOk()
            ->assertSee('Evaluación Manual Final');

        $response = $this->actingAs($monitor)
            ->post(route('evaluations.store_manual', $interaction), [
                'form_version_id' => $version->id,
                'items' => [
                    [
                        'subattribute_id' => $subAttribute->id,
                        'status' => 'compliant',
                        'notes' => 'Corrección validada por monitor.',
                    ],
                ],
            ]);

        $manualEvaluation = Evaluation::where('interaction_id', $interaction->id)
            ->where('type', 'manual')
            ->firstOrFail();

        $response->assertRedirect(route('evaluations.show', $manualEvaluation));
        $this->assertSame($monitor->id, $manualEvaluation->evaluator_id);
        $this->assertSame(100.0, (float) $manualEvaluation->percentage_score);
        $this->assertStringContainsString('Resumen de Corrección Manual', $manualEvaluation->ai_summary);
        $this->assertStringContainsString('Feedback IA Base', $manualEvaluation->ai_summary);

        $manualItem = $manualEvaluation->items()->firstOrFail();
        $this->assertSame('El agente valida correctamente al cliente.', $manualItem->evidence_quote);
        $this->assertSame('Corrección validada por monitor.', $manualItem->ai_notes);
    }

    public function test_opening_manual_correction_claims_ai_review_for_monitor(): void
    {
        [, $agent, , $version, $interaction, $subAttribute] = $this->scorableInteraction();
        $monitor = $this->userWithRole('qa_monitor');

        $aiEvaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $agent->id,
            'type' => 'ai',
            'evaluator_id' => $monitor->id,
            'total_score' => 80,
            'max_possible_score' => 100,
            'percentage_score' => 80,
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
        ]);

        $aiEvaluation->items()->create([
            'subattribute_id' => $subAttribute->id,
            'status' => 'non_compliant',
            'score' => 0,
            'max_score' => 1,
            'weighted_score' => 0,
            'confidence' => 0.9,
        ]);

        $this->actingAs($monitor)
            ->get(route('evaluations.create_manual', $interaction))
            ->assertOk()
            ->assertSee('Caso reservado para ti');

        $aiEvaluation->refresh();

        $this->assertSame($monitor->id, $aiEvaluation->review_claimed_by);
        $this->assertNotNull($aiEvaluation->review_claimed_at);
        $this->assertNotNull($aiEvaluation->review_claim_expires_at);
        $this->assertDatabaseHas('evaluation_audit_events', [
            'evaluation_id' => $aiEvaluation->id,
            'actor_id' => $monitor->id,
            'event' => 'manual_review_claimed',
        ]);
    }

    public function test_second_monitor_cannot_open_actively_claimed_manual_correction(): void
    {
        [, $agent, , $version, $interaction, $subAttribute] = $this->scorableInteraction();
        $firstMonitor = $this->userWithRole('qa_monitor');
        $secondMonitor = $this->userWithRole('qa_monitor');
        $secondMonitor->managedCampaigns()->attach($interaction->campaign_id);

        $aiEvaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $agent->id,
            'type' => 'ai',
            'evaluator_id' => $firstMonitor->id,
            'total_score' => 80,
            'max_possible_score' => 100,
            'percentage_score' => 80,
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
        ]);

        $aiEvaluation->items()->create([
            'subattribute_id' => $subAttribute->id,
            'status' => 'non_compliant',
            'score' => 0,
            'max_score' => 1,
            'weighted_score' => 0,
            'confidence' => 0.9,
        ]);

        $this->actingAs($firstMonitor)
            ->get(route('evaluations.create_manual', $interaction))
            ->assertOk();

        $this->actingAs($secondMonitor)
            ->get(route('evaluations.create_manual', $interaction))
            ->assertRedirect(route('work-queue.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseMissing('evaluations', [
            'interaction_id' => $interaction->id,
            'type' => 'manual',
        ]);
    }

    public function test_second_monitor_cannot_store_actively_claimed_manual_correction(): void
    {
        [, $agent, , $version, $interaction, $subAttribute] = $this->scorableInteraction();
        $firstMonitor = $this->userWithRole('qa_monitor');
        $secondMonitor = $this->userWithRole('qa_monitor');
        $secondMonitor->managedCampaigns()->attach($interaction->campaign_id);

        $aiEvaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $agent->id,
            'type' => 'ai',
            'evaluator_id' => $firstMonitor->id,
            'total_score' => 80,
            'max_possible_score' => 100,
            'percentage_score' => 80,
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            'review_claimed_by' => $firstMonitor->id,
            'review_claimed_at' => now(),
            'review_claim_expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->actingAs($secondMonitor)
            ->post(route('evaluations.store_manual', $interaction), [
                'form_version_id' => $version->id,
                'items' => [
                    [
                        'subattribute_id' => $subAttribute->id,
                        'status' => 'compliant',
                        'notes' => 'Intento de corrección.',
                    ],
                ],
            ]);

        $response
            ->assertRedirect(route('work-queue.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseMissing('evaluations', [
            'interaction_id' => $interaction->id,
            'type' => 'manual',
        ]);
        $this->assertSame($firstMonitor->id, $aiEvaluation->fresh()->review_claimed_by);
    }

    public function test_manual_correction_view_keeps_ai_notes_out_of_manual_notes_field(): void
    {
        [, $agent, , $version, $interaction, $subAttribute] = $this->scorableInteraction();
        $monitor = $this->userWithRole('qa_monitor');

        $aiEvaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $agent->id,
            'type' => 'ai',
            'evaluator_id' => $monitor->id,
            'total_score' => 0,
            'max_possible_score' => 100,
            'percentage_score' => 0,
            'status' => Evaluation::STATUS_PENDING_MONITOR_REVIEW,
        ]);

        $aiEvaluation->items()->create([
            'subattribute_id' => $subAttribute->id,
            'status' => 'non_compliant',
            'score' => 0,
            'max_score' => 1,
            'weighted_score' => 0,
            'confidence' => 0.9,
            'ai_notes' => 'La IA detectó incumplimiento, pero el monitor puede corregirlo.',
        ]);

        $response = $this->actingAs($monitor)
            ->get(route('evaluations.create_manual', $interaction))
            ->assertOk()
            ->assertSee('La IA detectó incumplimiento, pero el monitor puede corregirlo.');

        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*data-testid="manual-notes-'.$subAttribute->id.'"[^>]*>\s*<\/textarea>/',
            $response->getContent()
        );
    }

    public function test_manual_not_found_items_do_not_penalize_score(): void
    {
        [$admin, , , $version, $interaction, $subAttribute] = $this->scorableInteraction();
        $subAttribute->update(['weight_percent' => 50]);

        $notApplicableSubAttribute = QualitySubAttribute::create([
            'attribute_id' => $subAttribute->attribute_id,
            'name' => 'Criterio no aplicable',
            'weight_percent' => 50,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('evaluations.store_manual', $interaction), [
                'form_version_id' => $version->id,
                'items' => [
                    [
                        'subattribute_id' => $subAttribute->id,
                        'status' => 'compliant',
                        'notes' => 'Cumple el criterio.',
                    ],
                    [
                        'subattribute_id' => $notApplicableSubAttribute->id,
                        'status' => 'not_found',
                        'notes' => 'No aplica para esta llamada.',
                    ],
                ],
            ]);

        $manualEvaluation = Evaluation::where('interaction_id', $interaction->id)
            ->where('type', 'manual')
            ->firstOrFail();

        $response->assertRedirect(route('evaluations.show', $manualEvaluation));
        $this->assertSame(50.0, (float) $manualEvaluation->total_score);
        $this->assertSame(50.0, (float) $manualEvaluation->max_possible_score);
        $this->assertSame(100.0, (float) $manualEvaluation->percentage_score);

        $notApplicableItem = $manualEvaluation->items()
            ->where('subattribute_id', $notApplicableSubAttribute->id)
            ->firstOrFail();

        $this->assertSame(0.0, (float) $notApplicableItem->max_score);
        $this->assertSame(0.0, (float) $notApplicableItem->weighted_score);
    }

    private function evaluation(string $status): array
    {
        [$admin, $agent, $supervisor, $version, $interaction] = $this->scorableInteraction();

        $evaluation = Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $agent->id,
            'type' => 'ai',
            'total_score' => 80,
            'max_possible_score' => 100,
            'percentage_score' => 80,
            'status' => $status,
        ]);

        return [$admin, $agent, $evaluation, $supervisor];
    }

    private function scorableInteraction(): array
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
            'name' => 'Saludo',
            'weight' => 100,
        ]);
        $subAttribute = QualitySubAttribute::create([
            'attribute_id' => $attribute->id,
            'name' => 'Saluda al cliente',
            'weight_percent' => 100,
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

        return [$admin, $agent, $supervisor, $version, $interaction, $subAttribute];
    }

}
