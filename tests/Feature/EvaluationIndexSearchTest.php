<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Evaluation;
use App\Models\Interaction;
use App\Models\QualityForm;
use App\Models\QualityFormVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EvaluationIndexSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_interaction_uses_call_datetime_label(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('transcripts.create'))
            ->assertOk()
            ->assertSee('Fecha y Hora de la Llamada');
    }

    public function test_evaluation_index_splits_call_and_upload_dates(): void
    {
        $admin = $this->adminUser();
        $agent = User::factory()->create(['name' => 'Juliana']);
        $monitor = User::factory()->create(['name' => 'Monitor']);

        $this->evaluationFor($agent, $monitor, [
            'occurred_at' => '2026-07-07 12:34:00',
            'uploaded_at' => '2026-07-10 09:15:00',
        ]);

        $this->actingAs($admin)
            ->get(route('evaluations.index'))
            ->assertOk()
            ->assertSee('Fecha Llamada')
            ->assertSee('Fecha Subida')
            ->assertSee('07/07/2026')
            ->assertSee('10/07/2026');
    }

    public function test_evaluation_index_search_is_case_insensitive_and_uses_interaction_metadata(): void
    {
        $admin = $this->adminUser();
        $monitor = User::factory()->create(['name' => 'Monitor']);
        $matchingAgent = User::factory()->create(['name' => 'Juliana Miranda']);
        $otherAgent = User::factory()->create(['name' => 'Agente Externo']);

        $this->evaluationFor($matchingAgent, $monitor, [
            'call_sn' => 'SN-MiXtO-456',
            'product_name' => 'Renuncias Cliente',
        ]);
        $this->evaluationFor($otherAgent, $monitor, [
            'call_sn' => 'OTRO-999',
            'product_name' => 'Retenciones',
        ]);

        $this->actingAs($admin)
            ->get(route('evaluations.index', ['q' => 'sn-mixto']))
            ->assertOk()
            ->assertSee('Juliana Miranda')
            ->assertDontSee('Agente Externo');

        $this->actingAs($admin)
            ->get(route('evaluations.index', ['q' => 'renuncias']))
            ->assertOk()
            ->assertSee('Juliana Miranda')
            ->assertDontSee('Agente Externo');
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'create_transcripts', 'guard_name' => 'web']));
        $admin->assignRole('admin');

        return $admin;
    }

    private function evaluationFor(User $agent, User $monitor, array $interactionData = []): Evaluation
    {
        $campaign = Campaign::create(['name' => 'Falabella', 'type' => 'inbound', 'is_active' => true]);
        $form = QualityForm::create([
            'campaign_id' => $campaign->id,
            'name' => 'Ficha',
            'created_by' => $monitor->id,
        ]);
        $version = QualityFormVersion::create([
            'quality_form_id' => $form->id,
            'version_number' => 1,
            'status' => 'published',
            'is_active' => true,
            'published_at' => now(),
            'published_by' => $monitor->id,
        ]);

        $interaction = Interaction::create(array_merge([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $monitor->id,
            'occurred_at' => '2026-07-07 12:34:00',
            'uploaded_by' => $monitor->id,
            'file_path' => 'transcripts/test.txt',
            'file_name' => 'test.txt',
            'source_type' => 'audio',
            'channel' => 'call',
            'transcript_text' => 'Agente: saludo.',
            'status' => 'uploaded',
        ], $interactionData));

        if (isset($interactionData['uploaded_at'])) {
            $interaction->forceFill([
                'uploaded_at' => $interactionData['uploaded_at'],
                'created_at' => $interactionData['uploaded_at'],
                'updated_at' => $interactionData['uploaded_at'],
            ])->save();
        }

        return Evaluation::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $version->id,
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'type' => 'manual',
            'evaluator_id' => $monitor->id,
            'total_score' => 90,
            'max_possible_score' => 100,
            'percentage_score' => 90,
            'status' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
        ]);
    }
}
