<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\StaffingBatch;
use App\Models\StaffingMember;
use App\Models\User;
use App\Support\CsvImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StaffingController extends Controller
{
    public function store(Request $request)
    {
        $this->authorizeStaffingManagement();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'csv_file' => 'required|file|max:10240|mimes:csv,txt,xlsx,xls,ods',
            'sync_assignments' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $contents = CsvImport::contentsFromRequest($request);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        if (blank($contents)) {
            return back()->withInput()->with('error', 'Carga un archivo CSV o Excel con la dotación.');
        }

        $rows = CsvImport::rows($contents);

        if (empty($rows)) {
            return back()->withInput()->with('error', 'No se encontraron filas válidas en el CSV.');
        }

        $campaign = ! empty($validated['campaign_id'])
            ? Campaign::active()->operational()->whereKey($validated['campaign_id'])->first()
            : null;

        if (! empty($validated['campaign_id']) && ! $campaign) {
            return back()
                ->withInput()
                ->withErrors(['campaign_id' => 'Selecciona una subcampaña operativa o una campaña general sin subcampañas.']);
        }

        $stats = DB::transaction(function () use ($validated, $request, $rows, $campaign) {
            $batch = StaffingBatch::create([
                'name' => $validated['name'],
                'period_start' => $validated['period_start'] ?? null,
                'period_end' => $validated['period_end'] ?? null,
                'campaign_id' => $campaign?->id,
                'campaign_name' => $campaign?->displayName(),
                'status' => StaffingBatch::STATUS_ACTIVE,
                'source_filename' => $request->file('csv_file')?->getClientOriginalName(),
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            return $this->importMembers(
                batch: $batch,
                rows: $rows,
                forcedCampaign: $campaign,
                syncAssignments: $request->boolean('sync_assignments', true)
            );
        });

        return redirect()
            ->route('sampling.index')
            ->with('success', "Dotación cargada: {$stats['imported']} filas válidas, {$stats['skipped']} omitidas, {$stats['assignments']} asignaciones sincronizadas.");
    }

    public function template()
    {
        $rows = [
            [
                'codigo' => 'A001',
                'nombre' => 'Ana Pérez',
                'email' => 'ana.perez@empresa.com',
                'supervisor_codigo' => 'S001',
                'supervisor' => 'Rosa Díaz',
                'campania' => 'Atención',
                'subcampania' => 'Atención / Ventas',
                'cuartil' => 'Q2',
                'estado' => 'Activo',
            ],
        ];

        return CsvImport::download('plantilla_dotacion.csv', $rows);
    }

    public function templateExcel()
    {
        $rows = [
            [
                'codigo' => 'A001',
                'nombre' => 'Ana Pérez',
                'email' => 'ana.perez@empresa.com',
                'supervisor_codigo' => 'S001',
                'supervisor' => 'Rosa Díaz',
                'campania' => 'Atención',
                'subcampania' => 'Atención / Ventas',
                'cuartil' => 'Q2',
                'estado' => 'Activo',
            ],
        ];

        return CsvImport::downloadSpreadsheet('plantilla_dotacion.xlsx', $rows);
    }

    private function importMembers(StaffingBatch $batch, array $rows, ?Campaign $forcedCampaign, bool $syncAssignments): array
    {
        $imported = 0;
        $skipped = 0;
        $active = 0;
        $assignments = 0;
        $seenCodes = [];

        foreach ($rows as $row) {
            $code = CsvImport::value($row, ['codigo', 'code', 'employee_code', 'usuario', 'username', 'login']);
            $name = CsvImport::value($row, ['nombre', 'name', 'full_name', 'asesor', 'agente']);

            if (blank($code) || blank($name)) {
                $skipped++;
                continue;
            }

            $code = Str::upper(trim($code));
            if (isset($seenCodes[$code])) {
                $skipped++;
                continue;
            }
            $seenCodes[$code] = true;

            $supervisorCode = CsvImport::value($row, ['supervisor_codigo', 'codigo_supervisor', 'supervisor_code']);
            $supervisorName = CsvImport::value($row, ['supervisor', 'lider', 'jefe']);
            $campaignName = $forcedCampaign?->displayName() ?: CsvImport::value($row, ['subcampania', 'subcampaña', 'subcampaign', 'campania', 'campana', 'campaign']);
            $campaign = $forcedCampaign ?: $this->resolveCampaign($campaignName);
            $quartile = $this->normalizeQuartile(CsvImport::value($row, ['cuartil', 'quartile', 'q']));
            $status = $this->normalizeStatus(CsvImport::value($row, ['estado', 'status'], 'Activo'));
            $user = $this->resolveUser($code, CsvImport::value($row, ['email', 'correo']), $name);
            $supervisor = $this->resolveUser($supervisorCode, CsvImport::value($row, ['supervisor_email', 'correo_supervisor']), $supervisorName);

            $member = $batch->members()->create([
                'employee_code' => $code,
                'full_name' => trim($name),
                'user_id' => $user?->id,
                'supervisor_code' => $supervisorCode ? Str::upper(trim($supervisorCode)) : null,
                'supervisor_name' => $supervisorName,
                'supervisor_id' => $supervisor?->id,
                'campaign_id' => $campaign?->id,
                'campaign_name' => $campaign?->displayName() ?? $campaignName,
                'quartile' => $quartile,
                'status' => $status,
                'metadata' => $row,
            ]);

            $imported++;
            $active += $member->status === StaffingMember::STATUS_ACTIVE ? 1 : 0;

            if ($syncAssignments && $member->campaign_id && $member->user_id && $member->supervisor_id) {
                CampaignUserAssignment::updateOrCreate(
                    [
                        'campaign_id' => $member->campaign_id,
                        'agent_id' => $member->user_id,
                    ],
                    [
                        'supervisor_id' => $member->supervisor_id,
                        'is_active' => $member->status === StaffingMember::STATUS_ACTIVE,
                        'start_date' => $batch->period_start,
                        'end_date' => $member->status === StaffingMember::STATUS_ACTIVE ? null : ($batch->period_end ?? now()->toDateString()),
                    ]
                );
                $assignments++;
            }
        }

        $batch->update([
            'rows_count' => $imported,
            'active_count' => $active,
        ]);

        return compact('imported', 'skipped', 'assignments');
    }

    private function resolveCampaign(?string $name): ?Campaign
    {
        if (blank($name)) {
            return null;
        }

        $target = Str::lower(trim($name));

        return Campaign::query()
            ->active()
            ->operational()
            ->with('parent')
            ->get()
            ->first(function (Campaign $campaign) use ($target) {
                return Str::lower($campaign->name) === $target
                    || Str::lower($campaign->displayName()) === $target;
            });
    }

    private function resolveUser(?string $code, ?string $email, ?string $name): ?User
    {
        if (blank($code) && blank($email) && blank($name)) {
            return null;
        }

        return User::query()
            ->where(function ($query) use ($code, $email, $name) {
                if (filled($code)) {
                    $query->where('username', trim($code));
                }

                if (filled($email)) {
                    $query->orWhere('email', trim($email));
                }

                if (filled($name)) {
                    $query->orWhereRaw('LOWER(name) = ?', [Str::lower(trim($name))]);
                }
            })
            ->first();
    }

    private function normalizeQuartile(?string $value): ?string
    {
        $value = Str::upper(trim((string) $value));

        if (preg_match('/^[1-4]$/', $value)) {
            return 'Q'.$value;
        }

        return in_array($value, ['Q1', 'Q2', 'Q3', 'Q4'], true) ? $value : null;
    }

    private function normalizeStatus(?string $value): string
    {
        $value = Str::lower(trim((string) $value));

        return in_array($value, ['activo', 'active', '1', 'si', 'sí', 'true'], true)
            ? StaffingMember::STATUS_ACTIVE
            : StaffingMember::STATUS_INACTIVE;
    }

    private function authorizeStaffingManagement(): void
    {
        if (! auth()->user()->can('manage_staffing') && ! auth()->user()->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator'])) {
            abort(403);
        }
    }
}
