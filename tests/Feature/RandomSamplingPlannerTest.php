<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Interaction;
use App\Models\SamplingOrder;
use App\Models\StaffingBatch;
use App\Models\StaffingMember;
use App\Models\User;
use App\Services\RandomSamplingPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RandomSamplingPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_generates_weekly_orders_by_quartile(): void
    {
        $admin = $this->userWithRole('admin');
        $campaign = Campaign::create(['name' => 'Soporte']);

        $plan = app(RandomSamplingPlannerService::class)->createPlan([
            'name' => 'Semana QA',
            'week_start' => '2026-06-01',
            'business_days' => 'mon-fri',
            'start_hour' => '09:00',
            'end_hour' => '18:00',
            'campaign_id' => $campaign->id,
            'campaign_filter' => $campaign->name,
            'seed' => 'semilla-controlada',
            'quotas' => ['Q1' => 1, 'Q2' => 2, 'Q3' => 3, 'Q4' => 4],
            'unique_day' => true,
            'rotate_methods' => true,
            'staff_csv' => $this->staffCsv(),
        ], $admin);

        $this->assertSame(4, $plan->staff_count);
        $this->assertSame(10, $plan->orders_count);
        $this->assertDatabaseCount('sampling_orders', 10);

        $ordersByAdvisor = $plan->orders()->get()->groupBy('advisor_code');

        $this->assertSame(1, $ordersByAdvisor->get('A001')->count());
        $this->assertSame(2, $ordersByAdvisor->get('A002')->count());
        $this->assertSame(3, $ordersByAdvisor->get('A003')->count());
        $this->assertSame(4, $ordersByAdvisor->get('A004')->count());

        foreach ($ordersByAdvisor as $orders) {
            $this->assertCount($orders->count(), $orders->pluck('assigned_date')->unique());
        }
    }

    public function test_order_execution_can_be_registered_and_linked_to_interaction(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent', ['username' => 'A001', 'name' => 'Ana Perez']);
        $supervisor = $this->userWithRole('supervisor', ['name' => 'Supervisor Uno']);
        $campaign = Campaign::create(['name' => 'Soporte']);
        $batch = StaffingBatch::create([
            'name' => 'Dotación Soporte',
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'status' => StaffingBatch::STATUS_ACTIVE,
            'rows_count' => 1,
            'active_count' => 1,
            'created_by' => $admin->id,
        ]);
        $batch->members()->create([
            'employee_code' => 'A001',
            'full_name' => 'Ana Perez',
            'user_id' => $agent->id,
            'supervisor_name' => 'Supervisor Uno',
            'supervisor_id' => $supervisor->id,
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'quartile' => 'Q1',
            'status' => StaffingMember::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->post(route('sampling.store'), [
                'name' => 'Semana QA',
                'week_start' => '2026-06-01',
                'business_days' => 'mon-fri',
                'start_hour' => '09:00',
                'end_hour' => '18:00',
                'campaign_id' => $campaign->id,
                'staffing_batch_id' => $batch->id,
                'seed' => 'semilla-controlada',
                'quotas' => ['q1' => 1, 'q2' => 0, 'q3' => 0, 'q4' => 0],
                'unique_day' => '1',
                'rotate_methods' => '1',
            ])
            ->assertRedirect();

        $order = SamplingOrder::firstOrFail();

        $this->actingAs($admin)
            ->get(route('sampling.show', $order->plan))
            ->assertOk()
            ->assertSee('Órdenes de muestreo')
            ->assertSee($order->order_code);

        $interaction = Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => $order->assigned_date->copy()->setTime(10, 0),
            'uploaded_by' => $admin->id,
            'file_path' => 'transcripts/test.txt',
            'file_name' => 'test.txt',
            'call_sn' => 'SN-QA-001',
            'source_type' => 'text',
            'transcript_text' => 'Transcripcion de prueba',
            'status' => 'uploaded',
        ]);

        $this->actingAs($admin)
            ->post(route('sampling.orders.update', $order), [
                'status' => SamplingOrder::STATUS_APPLIED,
                'call_identifier' => 'SN-QA-001',
                'comment' => 'Orden ejecutada',
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame(SamplingOrder::STATUS_APPLIED, $order->status);
        $this->assertSame($interaction->id, $order->interaction_id);
        $this->assertSame('SN-QA-001', $order->call_identifier);
        $this->assertDatabaseCount('sampling_order_audit_events', 2);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function staffCsv(): string
    {
        return <<<'CSV'
codigo,nombre,supervisor,campania,cuartil,estado
A001,Ana Perez,Supervisor Uno,Soporte,Q1,Activo
A002,Bruno Diaz,Supervisor Uno,Soporte,Q2,Activo
A003,Camila Rojas,Supervisor Dos,Soporte,Q3,Activo
A004,Diego Soto,Supervisor Dos,Soporte,Q4,Activo
CSV;
    }
}
