<?php

namespace Tests\Feature;

use App\Models\Campaign;
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
