<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
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
        $campaigns = Campaign::active()->parents()->orderBy('name')->get();
        $subcampaigns = Campaign::active()
            ->subcampaigns()
            ->with('parent')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Campaign $campaign) => $campaign->displayName())
            ->values();
        $supervisors = $this->supervisorQuery()->orderBy('name')->get();

        return view('users.import', compact('roles', 'campaigns', 'subcampaigns', 'supervisors'));
    }

    public function create()
    {
        $roles = Role::all();
        $campaigns = Campaign::active()->orderedForSelect()->get();
        return view('users.create', compact('roles', 'campaigns'));
    }

    public function importStore(Request $request)
    {
        $validated = $request->validate([
            'csv_file' => 'required|file|max:10240|mimes:csv,txt,xlsx,xls,ods',
            'default_role' => 'nullable|string|exists:roles,name',
            'default_password' => 'nullable|string|min:8',
            'default_campaign_id' => 'nullable|exists:campaigns,id',
            'default_subcampaign_id' => 'nullable|exists:campaigns,id',
            'default_supervisor_id' => 'nullable|exists:users,id',
            'update_existing' => 'nullable|boolean',
            'sync_campaigns' => 'nullable|boolean',
        ]);

        if (! empty($validated['default_subcampaign_id']) && ! empty($validated['default_campaign_id'])) {
            $subcampaignBelongsToCampaign = Campaign::query()
                ->whereKey($validated['default_subcampaign_id'])
                ->where('parent_id', $validated['default_campaign_id'])
                ->exists();

            if (! $subcampaignBelongsToCampaign) {
                return back()
                    ->withInput()
                    ->withErrors(['default_subcampaign_id' => 'La subcampaña seleccionada no pertenece a la campaña general indicada.']);
            }
        } elseif (! empty($validated['default_subcampaign_id'])) {
            $isSubcampaign = Campaign::query()
                ->whereKey($validated['default_subcampaign_id'])
                ->whereNotNull('parent_id')
                ->exists();

            if (! $isSubcampaign) {
                return back()
                    ->withInput()
                    ->withErrors(['default_subcampaign_id' => 'Selecciona una subcampaña operativa válida.']);
            }
        } elseif (! empty($validated['default_campaign_id'])) {
            $requiresSubcampaign = Campaign::query()
                ->whereKey($validated['default_campaign_id'])
                ->whereHas('children')
                ->exists();

            if ($requiresSubcampaign) {
                return back()
                    ->withInput()
                    ->withErrors(['default_subcampaign_id' => 'Esta campaña general tiene subcampañas. Selecciona la subcampaña destino para asignar al personal.']);
            }
        }

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
                defaultCampaignId: $validated['default_campaign_id'] ?? null,
                defaultSubcampaignId: $validated['default_subcampaign_id'] ?? null,
                defaultSupervisorId: $validated['default_supervisor_id'] ?? null,
                updateExisting: $request->boolean('update_existing', true),
                syncCampaigns: $request->boolean('sync_campaigns', true)
            );
        });

        $assignmentMessage = $stats['assignments'] > 0
            ? ", {$stats['assignments']} asignaciones"
            : '';
        $assignmentSkippedMessage = $stats['assignment_skipped'] > 0
            ? ", {$stats['assignment_skipped']} sin asignar por falta de supervisor"
            : '';

        return redirect()
            ->route('users.index')
            ->with('success', "Importación completa: {$stats['created']} creados, {$stats['updated']} actualizados, {$stats['skipped']} omitidos{$assignmentMessage}{$assignmentSkippedMessage}.");
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
            'campaigns' => 'Claro',
            'subcampaigns' => 'Claro Upgrade',
            'supervisor_username' => 's001',
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
            'campaigns' => 'Claro',
            'subcampaigns' => 'Claro Upgrade',
            'supervisor_username' => 's001',
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
        $campaigns = Campaign::active()->orderedForSelect()->get();
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

        if ($this->userHasProtectedRecords($user)) {
            return back()->with('error', 'No se puede eliminar este usuario porque tiene historial operativo asociado.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario eliminado exitosamente.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ], [
            'user_ids.required' => 'Selecciona al menos un usuario.',
            'user_ids.min' => 'Selecciona al menos un usuario.',
        ]);

        $deleted = 0;
        $skipped = 0;

        DB::transaction(function () use ($validated, &$deleted, &$skipped) {
            User::whereIn('id', $validated['user_ids'])
                ->orderBy('id')
                ->get()
                ->each(function (User $user) use (&$deleted, &$skipped) {
                    if ($user->id === Auth::id() || $this->userHasProtectedRecords($user)) {
                        $skipped++;
                        return;
                    }

                    $user->delete();
                    $deleted++;
                });
        });

        $message = "Eliminación masiva: {$deleted} eliminados";
        if ($skipped > 0) {
            $message .= ", {$skipped} omitidos por seguridad o historial asociado";
        }

        return redirect()
            ->route('users.index')
            ->with($deleted > 0 ? 'success' : 'error', $message.'.');
    }

    private function importUsersFromRows(
        array $rows,
        ?string $defaultRole,
        ?string $defaultPassword,
        ?int $defaultCampaignId,
        ?int $defaultSubcampaignId,
        ?int $defaultSupervisorId,
        bool $updateExisting,
        bool $syncCampaigns
    ): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $assignments = 0;
        $assignmentSkipped = 0;

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

            if ($syncCampaigns) {
                $campaignIds = $this->campaignIdsForImport($row, $defaultCampaignId, $defaultSubcampaignId);

                if ($role === 'agent' && ! empty($campaignIds)) {
                    $supervisorId = $this->supervisorIdForImport($row, $defaultSupervisorId);

                    if ($supervisorId) {
                        $user->forceFill(['supervisor_id' => $supervisorId])->save();
                        $assignments += $this->syncAgentCampaignAssignments($user, $campaignIds, $supervisorId);
                    } else {
                        $assignmentSkipped++;
                    }
                }

                if (in_array($role, ['qa_monitor', 'qa_coordinator', 'manager'], true) && ! empty($campaignIds)) {
                    $user->managedCampaigns()->sync($campaignIds);
                }
            }

            $existing ? $updated++ : $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'assignments' => $assignments,
            'assignment_skipped' => $assignmentSkipped,
        ];
    }

    private function campaignIdsForImport(array $row, ?int $defaultCampaignId, ?int $defaultSubcampaignId): array
    {
        $campaignValue = CsvImport::value($row, [
            'campaigns',
            'campanias',
            'campañas',
            'campaign_ids',
            'campania',
            'campaña',
            'campaign',
        ]);

        $subcampaignValue = CsvImport::value($row, [
            'subcampaigns',
            'sub_campaigns',
            'subcampanias',
            'subcampañas',
            'subcampania',
            'subcampaña',
            'subcampaign',
            'sub_campaign',
        ]);

        $campaignIds = $this->campaignIdsFromCsvValues($campaignValue, $subcampaignValue);

        if ($defaultSubcampaignId) {
            array_unshift($campaignIds, $defaultSubcampaignId);
        } elseif ($defaultCampaignId) {
            array_unshift($campaignIds, $defaultCampaignId);
        }

        return collect($campaignIds)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function supervisorIdForImport(array $row, ?int $defaultSupervisorId): ?int
    {
        $supervisorId = CsvImport::value($row, ['supervisor_id']);

        if (filled($supervisorId) && $this->supervisorQuery()->whereKey((int) $supervisorId)->exists()) {
            return (int) $supervisorId;
        }

        $supervisorCode = CsvImport::value($row, ['supervisor_username', 'supervisor_codigo', 'codigo_supervisor', 'supervisor_code']);
        $supervisorEmail = CsvImport::value($row, ['supervisor_email', 'correo_supervisor']);
        $supervisorName = CsvImport::value($row, ['supervisor', 'lider', 'jefe']);

        if (filled($supervisorCode) || filled($supervisorEmail) || filled($supervisorName)) {
            $supervisor = $this->supervisorQuery()
                ->where(function ($query) use ($supervisorCode, $supervisorEmail, $supervisorName) {
                    if (filled($supervisorCode)) {
                        $code = trim($supervisorCode);
                        $normalizedCode = $this->makeUsername($code);

                        $query->where(function ($query) use ($code, $normalizedCode) {
                            $query->where('username', $code)
                                ->orWhereRaw('LOWER(username) = ?', [Str::lower($code)])
                                ->orWhere('username', $normalizedCode);
                        });
                    }

                    if (filled($supervisorEmail)) {
                        $query->orWhere('email', trim($supervisorEmail));
                    }

                    if (filled($supervisorName)) {
                        $query->orWhereRaw('LOWER(name) = ?', [Str::lower(trim($supervisorName))]);
                    }
                })
                ->first();

            if ($supervisor) {
                return $supervisor->id;
            }
        }

        return $defaultSupervisorId && $this->supervisorQuery()->whereKey($defaultSupervisorId)->exists()
            ? $defaultSupervisorId
            : null;
    }

    private function supervisorQuery()
    {
        return User::query()->whereHas('roles', fn ($query) => $query->where('name', 'supervisor'));
    }

    private function syncAgentCampaignAssignments(User $agent, array $campaignIds, int $supervisorId): int
    {
        $synced = 0;

        foreach ($campaignIds as $campaignId) {
            CampaignUserAssignment::updateOrCreate(
                [
                    'campaign_id' => $campaignId,
                    'agent_id' => $agent->id,
                ],
                [
                    'supervisor_id' => $supervisorId,
                    'is_active' => true,
                    'start_date' => now()->toDateString(),
                    'end_date' => null,
                ]
            );
            $synced++;
        }

        return $synced;
    }

    private function userHasProtectedRecords(User $user): bool
    {
        $userId = $user->id;

        return DB::table('interactions')
            ->where('agent_id', $userId)
            ->orWhere('supervisor_id', $userId)
            ->orWhere('uploaded_by', $userId)
            ->exists()
            || DB::table('evaluations')->where('agent_id', $userId)->exists()
            || DB::table('agent_responses')->where('agent_id', $userId)->exists()
            || DB::table('quality_forms')->where('created_by', $userId)->exists()
            || DB::table('insight_reports')->where('generated_by', $userId)->exists();
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

        $tokens = $this->csvCampaignTokens($value);

        $campaignIds = Campaign::query()
            ->whereIn('id', $tokens->filter(fn ($token) => ctype_digit($token))->map(fn ($token) => (int) $token))
            ->orWhereIn('name', $tokens)
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        $matchedIds = collect($campaignIds);
        $matchedLabels = Campaign::query()
            ->with('parent')
            ->active()
            ->get()
            ->filter(function (Campaign $campaign) use ($tokens) {
                return $tokens->contains(fn ($token) => Str::lower($token) === Str::lower($campaign->displayName()));
            })
            ->pluck('id');

        return $matchedIds
            ->merge($matchedLabels)
            ->unique()
            ->values()
            ->all();
    }

    private function campaignIdsFromCsvValues(?string $campaignValue, ?string $subcampaignValue): array
    {
        $campaignIds = [];

        if (filled($subcampaignValue)) {
            $campaignIds = $this->subcampaignIdsFromCsvValue($subcampaignValue, $campaignValue);
        }

        if (empty($campaignIds) && filled($campaignValue)) {
            $campaignIds = $this->campaignIdsFromCsvValue($campaignValue);
        }

        return collect($campaignIds)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function subcampaignIdsFromCsvValue(?string $value, ?string $parentValue = null): array
    {
        if (blank($value)) {
            return [];
        }

        $tokens = $this->csvCampaignTokens($value);
        $parentIds = collect($this->campaignIdsFromCsvValue($parentValue));

        return Campaign::query()
            ->active()
            ->operational()
            ->with('parent')
            ->get()
            ->filter(function (Campaign $campaign) use ($tokens, $parentIds) {
                if ($parentIds->isNotEmpty()
                    && ! $parentIds->contains($campaign->id)
                    && ! $parentIds->contains((int) $campaign->parent_id)
                ) {
                    return false;
                }

                return $tokens->contains(function ($token) use ($campaign) {
                    $normalized = Str::lower($token);

                    return (ctype_digit($token) && (int) $token === $campaign->id)
                        || $normalized === Str::lower($campaign->name)
                        || $normalized === Str::lower($campaign->displayName());
                });
            })
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

    private function csvCampaignTokens(?string $value): \Illuminate\Support\Collection
    {
        return collect(preg_split('/[\r\n;|,]+/', (string) $value))
            ->map(fn ($token) => trim((string) $token))
            ->filter()
            ->values();
    }

}
