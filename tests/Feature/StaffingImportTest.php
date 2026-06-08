<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\SamplingOrder;
use App\Models\StaffingBatch;
use App\Models\StaffingMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class StaffingImportTest extends TestCase
{
    use RefreshDatabase, CreatesUsersWithRoles;

    public function test_admin_can_import_staffing_and_sync_assignments(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent', ['username' => 'A001', 'name' => 'Ana Pérez', 'email' => 'ana.perez@empresa.com']);
        $supervisor = $this->userWithRole('supervisor', ['username' => 'S001', 'name' => 'Rosa Díaz']);
        $campaign = Campaign::create(['name' => 'Atención']);

        $csv = "codigo,nombre,email,supervisor_codigo,supervisor,campania,cuartil,estado\nA001,Ana Pérez,ana.perez@empresa.com,S001,Rosa Díaz,Atención,Q2,Activo";

        $this->actingAs($admin)
            ->post(route('sampling.staffing.store'), [
                'name' => 'Dotación semanal',
                'period_start' => '2026-06-01',
                'campaign_id' => $campaign->id,
                'csv_file' => $this->csvFile($csv, 'dotacion.csv'),
                'sync_assignments' => '1',
            ])
            ->assertRedirect(route('sampling.index'));

        $this->assertDatabaseHas('staffing_batches', [
            'name' => 'Dotación semanal',
            'rows_count' => 1,
            'active_count' => 1,
        ]);
        $this->assertDatabaseHas('staffing_members', [
            'employee_code' => 'A001',
            'user_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'campaign_id' => $campaign->id,
            'quartile' => 'Q2',
            'status' => StaffingMember::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('campaign_user_assignments', [
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        $this->assertSame(1, CampaignUserAssignment::count());
    }

    public function test_sampling_plan_can_use_registered_staffing_batch(): void
    {
        $admin = $this->userWithRole('admin');
        $this->userWithRole('supervisor', ['name' => 'Supervisor no asignado']);
        $campaign = Campaign::create(['name' => 'Atención']);
        $batch = StaffingBatch::create([
            'name' => 'Dotación QA',
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'status' => StaffingBatch::STATUS_ACTIVE,
            'rows_count' => 1,
            'active_count' => 1,
            'created_by' => $admin->id,
        ]);
        $batch->members()->create([
            'employee_code' => 'A001',
            'full_name' => 'Ana Pérez',
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'quartile' => 'Q1',
            'status' => StaffingMember::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->post(route('sampling.store'), [
                'name' => 'Muestreo con dotación',
                'week_start' => '2026-06-01',
                'business_days' => 'mon-fri',
                'start_hour' => '09:00',
                'end_hour' => '18:00',
                'campaign_id' => $campaign->id,
                'staffing_batch_id' => $batch->id,
                'seed' => 'dotacion',
                'quotas' => ['q1' => 1, 'q2' => 0, 'q3' => 0, 'q4' => 0],
                'unique_day' => '1',
                'rotate_methods' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sampling_plans', [
            'staffing_batch_id' => $batch->id,
            'staff_count' => 1,
            'orders_count' => 1,
        ]);
        $this->assertSame(1, SamplingOrder::count());
        $this->assertNull(SamplingOrder::firstOrFail()->supervisor_id);
    }

    public function test_qa_monitor_can_manage_sampling_staffing_and_plans(): void
    {
        $monitor = $this->userWithRole('qa_monitor');
        $campaign = Campaign::create(['name' => 'Atención']);

        $csv = "codigo,nombre,email,supervisor_codigo,supervisor,campania,cuartil,estado\nA001,Ana Pérez,ana.perez@empresa.com,,,Atención,Q1,Activo";

        $this->actingAs($monitor)
            ->post(route('sampling.staffing.store'), [
                'name' => 'Dotación monitor',
                'period_start' => '2026-06-01',
                'campaign_id' => $campaign->id,
                'csv_file' => $this->csvFile($csv, 'dotacion.csv'),
                'sync_assignments' => '0',
            ])
            ->assertRedirect(route('sampling.index'));

        $batch = StaffingBatch::firstOrFail();

        $this->actingAs($monitor)
            ->post(route('sampling.store'), [
                'name' => 'Muestreo monitor',
                'week_start' => '2026-06-01',
                'business_days' => 'mon-fri',
                'start_hour' => '09:00',
                'end_hour' => '18:00',
                'campaign_id' => $campaign->id,
                'staffing_batch_id' => $batch->id,
                'seed' => 'monitor',
                'quotas' => ['q1' => 1, 'q2' => 0, 'q3' => 0, 'q4' => 0],
                'unique_day' => '1',
                'rotate_methods' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sampling_plans', [
            'created_by' => $monitor->id,
            'staffing_batch_id' => $batch->id,
            'orders_count' => 1,
        ]);
    }

    public function test_sampling_index_filters_staffing_batches_by_subcampaign(): void
    {
        $admin = $this->userWithRole('admin');
        $parent = Campaign::create(['name' => 'Claro']);
        $upgrade = Campaign::create(['name' => 'Upgrade', 'parent_id' => $parent->id]);
        $prepago = Campaign::create(['name' => 'Prepago', 'parent_id' => $parent->id]);

        $upgradeBatch = StaffingBatch::create([
            'name' => 'Dotación Upgrade',
            'campaign_id' => $upgrade->id,
            'campaign_name' => $upgrade->displayName(),
            'status' => StaffingBatch::STATUS_ACTIVE,
            'rows_count' => 1,
            'active_count' => 1,
            'created_by' => $admin->id,
        ]);
        $prepagoBatch = StaffingBatch::create([
            'name' => 'Dotación Prepago',
            'campaign_id' => $prepago->id,
            'campaign_name' => $prepago->displayName(),
            'status' => StaffingBatch::STATUS_ACTIVE,
            'rows_count' => 1,
            'active_count' => 1,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('sampling.index', [
            'parent_campaign_id' => $parent->id,
            'campaign_id' => $upgrade->id,
        ]));

        $response->assertOk();
        $visibleBatchNames = $response->viewData('staffingBatches')->pluck('name')->all();

        $this->assertContains($upgradeBatch->name, $visibleBatchNames);
        $this->assertNotContains($prepagoBatch->name, $visibleBatchNames);
    }

    public function test_sampling_plan_rejects_staffing_batch_from_other_subcampaign(): void
    {
        $admin = $this->userWithRole('admin');
        $parent = Campaign::create(['name' => 'Claro']);
        $upgrade = Campaign::create(['name' => 'Upgrade', 'parent_id' => $parent->id]);
        $prepago = Campaign::create(['name' => 'Prepago', 'parent_id' => $parent->id]);
        $batch = StaffingBatch::create([
            'name' => 'Dotación Prepago',
            'campaign_id' => $prepago->id,
            'campaign_name' => $prepago->displayName(),
            'status' => StaffingBatch::STATUS_ACTIVE,
            'rows_count' => 1,
            'active_count' => 1,
            'created_by' => $admin->id,
        ]);
        $batch->members()->create([
            'employee_code' => 'A001',
            'full_name' => 'Ana Pérez',
            'campaign_id' => $prepago->id,
            'campaign_name' => $prepago->displayName(),
            'quartile' => 'Q1',
            'status' => StaffingMember::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->from(route('sampling.index'))
            ->post(route('sampling.store'), [
                'name' => 'Muestreo Upgrade',
                'week_start' => '2026-06-01',
                'business_days' => 'mon-fri',
                'start_hour' => '09:00',
                'end_hour' => '18:00',
                'campaign_id' => $upgrade->id,
                'staffing_batch_id' => $batch->id,
                'seed' => 'mismatch',
                'quotas' => ['q1' => 1, 'q2' => 0, 'q3' => 0, 'q4' => 0],
                'unique_day' => '1',
                'rotate_methods' => '1',
            ])
            ->assertRedirect(route('sampling.index'))
            ->assertSessionHasErrors('staffing_batch_id');

        $this->assertSame(0, SamplingOrder::count());
    }

    public function test_staffing_import_does_not_assign_supervisor_when_columns_are_empty(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent', ['username' => 'A002', 'name' => 'Bruno Díaz', 'email' => 'bruno.diaz@empresa.com']);
        $this->userWithRole('supervisor', ['username' => 'S999', 'name' => 'Supervisor no asignado']);
        $campaign = Campaign::create(['name' => 'Atención']);

        $csv = "codigo,nombre,email,supervisor_codigo,supervisor,campania,cuartil,estado\nA002,Bruno Díaz,bruno.diaz@empresa.com,,,Atención,Q1,Activo";

        $this->actingAs($admin)
            ->post(route('sampling.staffing.store'), [
                'name' => 'Dotación sin supervisor',
                'period_start' => '2026-06-01',
                'campaign_id' => $campaign->id,
                'csv_file' => $this->csvFile($csv, 'dotacion.csv'),
                'sync_assignments' => '1',
            ])
            ->assertRedirect(route('sampling.index'));

        $this->assertDatabaseHas('staffing_members', [
            'employee_code' => 'A002',
            'user_id' => $agent->id,
            'supervisor_id' => null,
            'campaign_id' => $campaign->id,
        ]);
        $this->assertSame(0, CampaignUserAssignment::count());
    }

    public function test_admin_can_import_staffing_from_excel_file(): void
    {
        $admin = $this->userWithRole('admin');
        $campaign = Campaign::create(['name' => 'Atención']);

        $file = $this->excelFile([
            ['codigo', 'nombre', 'email', 'supervisor_codigo', 'supervisor', 'campania', 'cuartil', 'estado'],
            ['A010', 'Julia Rojas', 'julia.rojas@empresa.com', 'S010', 'Marco Silva', 'Atención', 'Q3', 'Activo'],
        ]);

        $this->actingAs($admin)
            ->post(route('sampling.staffing.store'), [
                'name' => 'Dotación Excel',
                'period_start' => '2026-06-01',
                'campaign_id' => $campaign->id,
                'csv_file' => $file,
                'sync_assignments' => '0',
            ])
            ->assertRedirect(route('sampling.index'));

        $this->assertDatabaseHas('staffing_batches', [
            'name' => 'Dotación Excel',
            'rows_count' => 1,
            'active_count' => 1,
            'source_filename' => 'dotacion.xlsx',
        ]);
        $this->assertDatabaseHas('staffing_members', [
            'employee_code' => 'A010',
            'full_name' => 'Julia Rojas',
            'campaign_id' => $campaign->id,
            'quartile' => 'Q3',
            'status' => StaffingMember::STATUS_ACTIVE,
        ]);
    }

    private function excelFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1), $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'staffing-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'dotacion.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function csvFile(string $contents, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'staffing-').'.csv';
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }
}
