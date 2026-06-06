<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Interaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserBulkImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_manually(): void
    {
        $admin = $this->userWithRole('admin');
        Role::firstOrCreate(['name' => 'qa_monitor', 'guard_name' => 'web']);
        $campaign = Campaign::create(['name' => 'Atención']);

        $this->actingAs($admin)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('Crear Nuevo Usuario')
            ->assertSee('Foto de Perfil')
            ->assertSee('Acceso al Sistema')
            ->assertSee('Información de Contacto')
            ->assertSee('Importación masiva');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'username' => 'manual01',
                'name' => 'María',
                'paternal_surname' => 'Rojas',
                'maternal_surname' => 'López',
                'email' => 'maria.rojas@empresa.com',
                'personal_email' => 'maria.rojas@gmail.com',
                'company_phone' => '999000001',
                'personal_phone' => '999000002',
                'birthdate' => '1990-01-10',
                'gender' => 'F',
                'address' => 'Av. Principal 123',
                'department' => 'Lima',
                'province' => 'Lima',
                'district' => 'Miraflores',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'role' => 'qa_monitor',
                'campaign_ids' => [$campaign->id],
            ])
            ->assertRedirect(route('users.index'));

        $user = User::where('username', 'manual01')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'username' => 'manual01',
            'email' => 'maria.rojas@empresa.com',
            'department' => 'Lima',
            'district' => 'Miraflores',
        ]);
        $this->assertTrue(Hash::check('Password123', $user->password));
        $this->assertTrue($user->hasRole('qa_monitor'));
        $this->assertTrue($user->managedCampaigns()->whereKey($campaign->id)->exists());
    }

    public function test_admin_can_view_bulk_import_screen(): void
    {
        $admin = $this->userWithRole('admin');
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
        Campaign::create(['name' => 'Atención']);

        $this->actingAs($admin)
            ->get(route('users.import'))
            ->assertOk()
            ->assertSee('Archivo y reglas')
            ->assertSee('Seleccionar archivo')
            ->assertSee('Subcampaña destino')
            ->assertSee('Columnas esperadas')
            ->assertSee('Nuevo usuario');
    }

    public function test_admin_can_bulk_import_users_and_assign_campaigns(): void
    {
        $admin = $this->userWithRole('admin');
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'qa_monitor', 'guard_name' => 'web']);
        $campaign = Campaign::create(['name' => 'Atención']);

        $csv = "username,name,email,role,password,campaigns\na001,Ana Pérez,ana.perez@empresa.com,agent,Password123,\nqa01,Monitor QA,monitor.qa@empresa.com,qa_monitor,Password123,Atención";

        $this->actingAs($admin)
            ->post(route('users.import.store'), [
                'csv_file' => $this->csvFile($csv, 'usuarios.csv'),
                'update_existing' => '1',
                'sync_campaigns' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'a001',
            'email' => 'ana.perez@empresa.com',
        ]);
        $monitor = User::where('username', 'qa01')->firstOrFail();

        $this->assertTrue($monitor->hasRole('qa_monitor'));
        $this->assertTrue($monitor->managedCampaigns()->whereKey($campaign->id)->exists());
    }

    public function test_admin_can_bulk_import_agents_into_selected_campaign(): void
    {
        $admin = $this->userWithRole('admin');
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
        $supervisor = $this->userWithRole('supervisor', ['username' => 's001', 'name' => 'Rosa Díaz']);
        $campaign = Campaign::create(['name' => 'Claro']);

        $csv = "username,name,email,role,password\na001,Ana Pérez,ana.perez@empresa.com,agent,Password123";

        $this->actingAs($admin)
            ->post(route('users.import.store'), [
                'csv_file' => $this->csvFile($csv, 'usuarios.csv'),
                'default_campaign_id' => $campaign->id,
                'default_supervisor_id' => $supervisor->id,
                'update_existing' => '1',
                'sync_campaigns' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $agent = User::where('username', 'a001')->firstOrFail();

        $this->assertTrue($agent->hasRole('agent'));
        $this->assertSame($supervisor->id, $agent->supervisor_id);
        $this->assertDatabaseHas('campaign_user_assignments', [
            'campaign_id' => $campaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_bulk_import_agents_into_selected_subcampaign(): void
    {
        $admin = $this->userWithRole('admin');
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
        $supervisor = $this->userWithRole('supervisor', ['username' => 's001', 'name' => 'Rosa Díaz']);
        $parent = Campaign::create(['name' => 'Claro']);
        $subcampaign = Campaign::create(['name' => 'Claro Upgrade', 'parent_id' => $parent->id]);

        $csv = "username,name,email,role,password\na001,Ana Pérez,ana.perez@empresa.com,agent,Password123";

        $this->actingAs($admin)
            ->post(route('users.import.store'), [
                'csv_file' => $this->csvFile($csv, 'usuarios.csv'),
                'default_campaign_id' => $parent->id,
                'default_subcampaign_id' => $subcampaign->id,
                'default_supervisor_id' => $supervisor->id,
                'update_existing' => '1',
                'sync_campaigns' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $agent = User::where('username', 'a001')->firstOrFail();

        $this->assertDatabaseHas('campaign_user_assignments', [
            'campaign_id' => $subcampaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseMissing('campaign_user_assignments', [
            'campaign_id' => $parent->id,
            'agent_id' => $agent->id,
        ]);
    }

    public function test_bulk_import_resolves_subcampaign_column_with_parent_context(): void
    {
        $admin = $this->userWithRole('admin');
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
        $supervisor = $this->userWithRole('supervisor', ['username' => 's001', 'name' => 'Rosa Díaz']);
        $parent = Campaign::create(['name' => 'Claro']);
        $subcampaign = Campaign::create(['name' => 'Upgrade', 'parent_id' => $parent->id]);

        $csv = "username,name,email,role,password,campaigns,subcampaigns,supervisor_username\n"
            ."a001,Ana Pérez,ana.perez@empresa.com,agent,Password123,Claro,Upgrade,s001";

        $this->actingAs($admin)
            ->post(route('users.import.store'), [
                'csv_file' => $this->csvFile($csv, 'usuarios.csv'),
                'update_existing' => '1',
                'sync_campaigns' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $agent = User::where('username', 'a001')->firstOrFail();

        $this->assertDatabaseHas('campaign_user_assignments', [
            'campaign_id' => $subcampaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseMissing('campaign_user_assignments', [
            'campaign_id' => $parent->id,
            'agent_id' => $agent->id,
        ]);
    }

    public function test_bulk_import_resolves_normalized_supervisor_username(): void
    {
        $admin = $this->userWithRole('admin');
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $parent = Campaign::create(['name' => 'Claro']);
        $subcampaign = Campaign::create(['name' => 'Prepago', 'parent_id' => $parent->id]);

        $csv = "username,name,email,role,password,supervisor_username\n"
            ."E8179303,Beathriz Haro,bharo@example.com,supervisor,Password123,\n"
            ."E8179344,Ada Vilcaluri,ada@example.com,agent,Password123,E8179303";

        $this->actingAs($admin)
            ->post(route('users.import.store'), [
                'csv_file' => $this->csvFile($csv, 'usuarios.csv'),
                'default_campaign_id' => $parent->id,
                'default_subcampaign_id' => $subcampaign->id,
                'update_existing' => '1',
                'sync_campaigns' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $supervisor = User::where('username', 'e8179303')->firstOrFail();
        $agent = User::where('username', 'e8179344')->firstOrFail();

        $this->assertSame($supervisor->id, $agent->supervisor_id);
        $this->assertDatabaseHas('campaign_user_assignments', [
            'campaign_id' => $subcampaign->id,
            'agent_id' => $agent->id,
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_bulk_delete_users_and_skip_protected_records(): void
    {
        $admin = $this->userWithRole('admin');
        $deleteableOne = $this->userWithRole('agent', ['username' => 'delete01']);
        $deleteableTwo = $this->userWithRole('agent', ['username' => 'delete02']);
        $protectedAgent = $this->userWithRole('agent', ['username' => 'keep01']);
        $supervisor = $this->userWithRole('supervisor', ['username' => 'sup01']);
        $campaign = Campaign::create(['name' => 'Claro']);

        Interaction::create([
            'campaign_id' => $campaign->id,
            'agent_id' => $protectedAgent->id,
            'supervisor_id' => $supervisor->id,
            'occurred_at' => '2026-06-04 10:00:00',
            'uploaded_by' => $admin->id,
            'file_path' => 'transcripts/protected.txt',
            'file_name' => 'protected.txt',
            'transcript_text' => 'Agente: prueba. Cliente: respuesta.',
            'status' => 'uploaded',
        ]);

        $this->actingAs($admin)
            ->delete(route('users.bulk-destroy'), [
                'user_ids' => [
                    $deleteableOne->id,
                    $deleteableTwo->id,
                    $protectedAgent->id,
                    $admin->id,
                ],
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', ['id' => $deleteableOne->id]);
        $this->assertDatabaseMissing('users', ['id' => $deleteableTwo->id]);
        $this->assertDatabaseHas('users', ['id' => $protectedAgent->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_bulk_import_updates_existing_user_when_enabled(): void
    {
        $admin = $this->userWithRole('admin');
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
        $user = $this->userWithRole('agent', [
            'username' => 'a001',
            'email' => 'old@example.com',
            'name' => 'Nombre anterior',
        ]);

        $csv = "username,name,email,role\na001,Ana Actualizada,old@example.com,agent";

        $this->actingAs($admin)
            ->post(route('users.import.store'), [
                'csv_file' => $this->csvFile($csv, 'usuarios.csv'),
                'default_password' => 'Password123',
                'update_existing' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertSame('Ana Actualizada', $user->fresh()->name);
    }

    public function test_admin_can_bulk_import_users_from_excel(): void
    {
        $admin = $this->userWithRole('admin');
        Role::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);

        $file = $this->excelFile([
            ['username', 'name', 'email', 'role', 'password'],
            ['a010', 'Julia Rojas', 'julia.rojas@empresa.com', 'agent', 'Password123'],
        ]);

        $this->actingAs($admin)
            ->post(route('users.import.store'), [
                'csv_file' => $file,
                'update_existing' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'a010',
            'email' => 'julia.rojas@empresa.com',
        ]);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function csvFile(string $contents, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'users-').'.csv';
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, 'text/csv', null, true);
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

        $path = tempnam(sys_get_temp_dir(), 'users-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'usuarios.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
