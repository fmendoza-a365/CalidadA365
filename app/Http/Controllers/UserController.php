<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CsvImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = User::with('roles')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim($request->string('q')->toString());

                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('paternal_surname', 'like', "%{$term}%")
                        ->orWhere('maternal_surname', 'like', "%{$term}%")
                        ->orWhere('username', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('department', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->role($request->string('role')->toString()))
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    public function import()
    {
        $roles = Role::orderBy('name')->get();
        $campaigns = \App\Models\Campaign::active()->orderBy('name')->get();

        return view('users.import', compact('roles', 'campaigns'));
    }

    public function create()
    {
        $roles = Role::all();
        $campaigns = \App\Models\Campaign::active()->orderBy('name')->get();
        return view('users.create', compact('roles', 'campaigns'));
    }

    public function importStore(Request $request)
    {
        $validated = $request->validate([
            'csv_file' => 'required|file|max:10240|mimes:csv,txt,xlsx,xls,ods',
            'default_role' => 'nullable|string|exists:roles,name',
            'default_password' => 'nullable|string|min:8',
            'update_existing' => 'nullable|boolean',
            'sync_campaigns' => 'nullable|boolean',
        ]);

        try {
            $contents = CsvImport::contentsFromRequest($request);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        if (blank($contents)) {
            return back()->withInput()->with('error', 'Carga un archivo CSV o Excel con usuarios.');
        }

        $rows = CsvImport::rows($contents);
        if (empty($rows)) {
            return back()->withInput()->with('error', 'No se encontraron filas válidas en el archivo.');
        }

        $stats = DB::transaction(function () use ($rows, $validated, $request) {
            return $this->importUsersFromRows(
                rows: $rows,
                defaultRole: $validated['default_role'] ?? null,
                defaultPassword: $validated['default_password'] ?? null,
                updateExisting: $request->boolean('update_existing', true),
                syncCampaigns: $request->boolean('sync_campaigns', true)
            );
        });

        return redirect()
            ->route('users.index')
            ->with('success', "Importación completa: {$stats['created']} creados, {$stats['updated']} actualizados, {$stats['skipped']} omitidos.");
    }

    public function importTemplate()
    {
        $rows = [[
            'username' => 'a001',
            'name' => 'Ana Pérez',
            'paternal_surname' => 'Pérez',
            'maternal_surname' => '',
            'email' => 'ana.perez@empresa.com',
            'role' => 'agent',
            'password' => '',
            'company_phone' => '999000000',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Miraflores',
            'campaigns' => 'Atención;Ventas',
        ]];

        return CsvImport::download('plantilla_usuarios.csv', $rows);
    }

    public function importTemplateExcel()
    {
        $rows = [[
            'username' => 'a001',
            'name' => 'Ana Pérez',
            'paternal_surname' => 'Pérez',
            'maternal_surname' => '',
            'email' => 'ana.perez@empresa.com',
            'role' => 'agent',
            'password' => '',
            'company_phone' => '999000000',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Miraflores',
            'campaigns' => 'Atención;Ventas',
        ]];

        return CsvImport::downloadSpreadsheet('plantilla_usuarios.xlsx', $rows);
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'name' => 'required|string|max:255',
            'paternal_surname' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'personal_email' => 'nullable|string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|exists:roles,name',
            'profile_photo' => 'nullable|image|max:2048', // 2MB Max
            'campaign_ids' => 'nullable|array',
            'campaign_ids.*' => 'exists:campaigns,id',
        ]);

        $userData = $request->except(['password', 'password_confirmation', 'role', 'profile_photo']);
        $userData['password'] = Hash::make($request->password);

        // Handle Photo Upload
        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $userData['profile_photo_path'] = $path;
        }

        $user = User::create($userData);
        $user->assignRole($request->role);

        if ($request->has('campaign_ids') && in_array($request->role, ['qa_monitor', 'qa_coordinator', 'manager'])) {
            $user->managedCampaigns()->sync($request->campaign_ids);
        }

        return redirect()->route('users.index')
            ->with('success', 'Usuario creado exitosamente.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $campaigns = \App\Models\Campaign::active()->orderBy('name')->get();
        $user->load('managedCampaigns');
        return view('users.edit', compact('user', 'roles', 'campaigns'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'name' => 'required|string|max:255',
            'paternal_surname' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|string|exists:roles,name',
            'profile_photo' => 'nullable|image|max:2048',
            'campaign_ids' => 'nullable|array',
            'campaign_ids.*' => 'exists:campaigns,id',
        ]);

        $userData = $request->except(['password', 'password_confirmation', 'role', 'profile_photo']);

        if ($request->filled('password')) {
            $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ]);
            $userData['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($user->profile_photo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_photo_path);
            }
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $userData['profile_photo_path'] = $path;
        }

        $user->update($userData);
        $user->syncRoles([$request->role]);

        if (in_array($request->role, ['qa_monitor', 'qa_coordinator', 'manager'])) {
            $user->managedCampaigns()->sync($request->campaign_ids ?? []);
        } else {
            $user->managedCampaigns()->detach();
        }

        return redirect()->route('users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario eliminado exitosamente.');
    }

    private function importUsersFromRows(array $rows, ?string $defaultRole, ?string $defaultPassword, bool $updateExisting, bool $syncCampaigns): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $name = CsvImport::value($row, ['name', 'nombre', 'nombres']);
            $email = CsvImport::value($row, ['email', 'correo', 'correo_empresa']);
            $username = CsvImport::value($row, ['username', 'usuario', 'login', 'codigo']);
            $role = CsvImport::value($row, ['role', 'rol'], $defaultRole);
            $password = CsvImport::value($row, ['password', 'contrasena', 'contraseña'], $defaultPassword);

            if (blank($name) || blank($role) || ! Role::where('name', $role)->exists()) {
                $skipped++;
                continue;
            }

            $username = $username ?: $this->makeUsername($email ?: $name);
            $email = $email ?: $username.'@qa365.local';

            $existing = User::query()
                ->where('username', $username)
                ->orWhere('email', $email)
                ->first();

            if ($existing && ! $updateExisting) {
                $skipped++;
                continue;
            }

            if (! $existing && blank($password)) {
                $skipped++;
                continue;
            }

            $userData = [
                'username' => $this->uniqueUsername($username, $existing?->id),
                'name' => $name,
                'paternal_surname' => CsvImport::value($row, ['paternal_surname', 'apellido_paterno']),
                'maternal_surname' => CsvImport::value($row, ['maternal_surname', 'apellido_materno']),
                'email' => $this->uniqueEmail($email, $existing?->id),
                'personal_email' => CsvImport::value($row, ['personal_email', 'correo_personal']),
                'personal_phone' => CsvImport::value($row, ['personal_phone', 'telefono_personal']),
                'company_phone' => CsvImport::value($row, ['company_phone', 'telefono_empresa', 'telefono']),
                'department' => CsvImport::value($row, ['department', 'departamento']),
                'province' => CsvImport::value($row, ['province', 'provincia']),
                'district' => CsvImport::value($row, ['district', 'distrito']),
                'address' => CsvImport::value($row, ['address', 'direccion']),
            ];

            if (filled($password)) {
                $userData['password'] = Hash::make($password);
            }

            $user = $existing
                ? tap($existing)->update($userData)
                : User::create($userData);

            $user->syncRoles([$role]);

            if ($syncCampaigns && in_array($role, ['qa_monitor', 'qa_coordinator', 'manager'], true)) {
                $campaignIds = $this->campaignIdsFromCsvValue(CsvImport::value($row, ['campaigns', 'campanias', 'campañas', 'campaign_ids']));
                if (! empty($campaignIds)) {
                    $user->managedCampaigns()->sync($campaignIds);
                }
            }

            $existing ? $updated++ : $created++;
        }

        return compact('created', 'updated', 'skipped');
    }

    private function makeUsername(string $value): string
    {
        return Str::of($value)
            ->before('@')
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->toString() ?: 'usuario';
    }

    private function uniqueUsername(string $username, ?int $ignoreId = null): string
    {
        $base = $this->makeUsername($username);
        $candidate = $base;
        $counter = 1;

        while (User::query()->where('username', $candidate)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $candidate = $base.$counter++;
        }

        return $candidate;
    }

    private function uniqueEmail(string $email, ?int $ignoreId = null): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, 'qa365.local');
        $base = $local;
        $candidate = $email;
        $counter = 1;

        while (User::query()->where('email', $candidate)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $candidate = $base.$counter++.'@'.$domain;
        }

        return $candidate;
    }

    private function campaignIdsFromCsvValue(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        $tokens = collect(preg_split('/[;|]+/', $value))
            ->map(fn ($token) => trim((string) $token))
            ->filter()
            ->values();

        return \App\Models\Campaign::query()
            ->whereIn('id', $tokens->filter(fn ($token) => ctype_digit($token))->map(fn ($token) => (int) $token))
            ->orWhereIn('name', $tokens)
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

}
