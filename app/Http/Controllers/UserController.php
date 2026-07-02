<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\User;
use App\Services\UserImportService;
use App\Support\CsvImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with([
            'roles',
            'agentAssignments.campaign.parent',
            'agentAssignments.supervisor',
            'managedCampaigns.parent'
        ])
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
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    public function import(UserImportService $importService)
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
        $supervisors = $importService->supervisorQuery()->orderBy('name')->get();

        return view('users.import', compact('roles', 'campaigns', 'subcampaigns', 'supervisors'));
    }

    public function create()
    {
        $roles = Role::all();
        $campaigns = Campaign::active()->orderedForSelect()->get();

        return view('users.create', compact('roles', 'campaigns'));
    }

    public function importStore(Request $request, UserImportService $importService)
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
                return back()->withInput()
                    ->withErrors(['default_subcampaign_id' => 'La subcampaña seleccionada no pertenece a la campaña general indicada.']);
            }
        } elseif (! empty($validated['default_subcampaign_id'])) {
            $isSubcampaign = Campaign::query()
                ->whereKey($validated['default_subcampaign_id'])
                ->whereNotNull('parent_id')
                ->exists();

            if (! $isSubcampaign) {
                return back()->withInput()
                    ->withErrors(['default_subcampaign_id' => 'Selecciona una subcampaña operativa válida.']);
            }
        } elseif (! empty($validated['default_campaign_id'])) {
            $requiresSubcampaign = Campaign::query()
                ->whereKey($validated['default_campaign_id'])
                ->whereHas('children')
                ->exists();

            if ($requiresSubcampaign) {
                return back()->withInput()
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

        $stats = DB::transaction(function () use ($rows, $validated, $request, $importService) {
            return $importService->importUsersFromRows(
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

        $assignmentMessage = $stats['assignments'] > 0 ? ", {$stats['assignments']} asignaciones" : '';
        $assignmentSkippedMessage = $stats['assignment_skipped'] > 0 ? ", {$stats['assignment_skipped']} sin asignar por falta de supervisor" : '';

        return redirect()
            ->route('users.index')
            ->with('success', "Importación completa: {$stats['created']} creados, {$stats['updated']} actualizados, {$stats['skipped']} omitidos{$assignmentMessage}{$assignmentSkippedMessage}.");
    }

    public function importTemplate()
    {
        $rows = [[
            'username' => 'a001', 'name' => 'Ana Pérez', 'paternal_surname' => 'Pérez',
            'maternal_surname' => '', 'email' => 'ana.perez@empresa.com', 'role' => 'agent',
            'password' => '', 'company_phone' => '999000000', 'department' => 'Lima',
            'province' => 'Lima', 'district' => 'Miraflores', 'campaigns' => 'Claro',
            'subcampaigns' => 'Claro Upgrade', 'supervisor_username' => 's001',
        ]];

        return CsvImport::download('plantilla_usuarios.csv', $rows);
    }

    public function importTemplateExcel()
    {
        $rows = [[
            'username' => 'a001', 'name' => 'Ana Pérez', 'paternal_surname' => 'Pérez',
            'maternal_surname' => '', 'email' => 'ana.perez@empresa.com', 'role' => 'agent',
            'password' => '', 'company_phone' => '999000000', 'department' => 'Lima',
            'province' => 'Lima', 'district' => 'Miraflores', 'campaigns' => 'Claro',
            'subcampaigns' => 'Claro Upgrade', 'supervisor_username' => 's001',
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
            'profile_photo' => 'nullable|image|max:2048',
            'campaign_ids' => 'nullable|array',
            'campaign_ids.*' => 'exists:campaigns,id',
            'status' => 'nullable|string|in:Activo,Baja',
        ]);

        $userData = $request->except(['password', 'password_confirmation', 'role', 'profile_photo']);
        $userData['password'] = Hash::make($request->password);
        $userData['status'] = $request->input('status') ?: 'Activo';

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

    public function edit(User $user)
    {
        $roles = Role::all();
        $campaigns = Campaign::active()->orderedForSelect()->get();
        $user->load('managedCampaigns');

        return view('users.edit', compact('user', 'roles', 'campaigns'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'username' => 'required|string|max:255|unique:users,username,'.$user->id,
            'name' => 'required|string|max:255',
            'paternal_surname' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'role' => 'required|string|exists:roles,name',
            'profile_photo' => 'nullable|image|max:2048',
            'campaign_ids' => 'nullable|array',
            'campaign_ids.*' => 'exists:campaigns,id',
            'status' => 'nullable|string|in:Activo,Baja',
        ]);

        $userData = $request->except(['password', 'password_confirmation', 'role', 'profile_photo']);
        $userData['status'] = $request->input('status') ?: $user->status;

        if ($request->filled('password')) {
            $request->validate(['password' => 'required|string|min:8|confirmed']);
            $userData['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('profile_photo')) {
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
}
