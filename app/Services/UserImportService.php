<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignUserAssignment;
use App\Models\User;
use App\Support\CsvImport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserImportService
{
    public function importUsersFromRows(
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

        $usernameMap = $this->buildUsernameMap($rows);

        // Process supervisors and administrative roles before agents so agent
        // rows can resolve supervisors created by the same import batch.
        usort($rows, function ($a, $b) {
            $roleA = CsvImport::value($a, ['role', 'rol'], 'agent');
            $roleB = CsvImport::value($b, ['role', 'rol'], 'agent');

            if ($roleA === 'agent' && $roleB !== 'agent') {
                return 1;
            }

            if ($roleA !== 'agent' && $roleB === 'agent') {
                return -1;
            }

            return 0;
        });

        foreach ($rows as $row) {
            $name = CsvImport::value($row, ['name', 'nombre', 'nombres']);
            $paternal = CsvImport::value($row, ['paternal_surname', 'apellido_paterno']);
            $maternal = CsvImport::value($row, ['maternal_surname', 'apellido_materno']);
            $email = CsvImport::value($row, ['email', 'correo', 'correo_empresa']);
            $username = CsvImport::value($row, ['username', 'usuario', 'login', 'codigo']);
            $role = CsvImport::value($row, ['role', 'rol'], $defaultRole);
            $password = CsvImport::value($row, ['password', 'contrasena', 'contraseña'], $defaultPassword);

            if (blank($name) || blank($role) || ! Role::where('name', $role)->exists()) {
                $skipped++;
                continue;
            }

            if (filled($paternal)) {
                $username = $this->generateFormulaUsername($name, $paternal, $maternal);
            } else {
                $username = $username ?: $this->makeUsername($email ?: $name);
            }
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
                'paternal_surname' => $paternal,
                'maternal_surname' => $maternal,
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
                    $supervisorId = $this->supervisorIdForImport($row, $defaultSupervisorId, $usernameMap);

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

    public function campaignIdsForImport(array $row, ?int $defaultCampaignId, ?int $defaultSubcampaignId): array
    {
        $campaignValue = CsvImport::value($row, [
            'campaigns', 'campanias', 'campañas', 'campaign_ids',
            'campania', 'campaña', 'campaign',
        ]);

        $subcampaignValue = CsvImport::value($row, [
            'subcampaigns', 'sub_campaigns', 'subcampanias', 'subcampañas',
            'subcampania', 'subcampaña', 'subcampaign', 'sub_campaign',
        ]);

        $campaignIds = $this->campaignIdsFromCsvValues($campaignValue, $subcampaignValue);

        if ($defaultSubcampaignId) {
            array_unshift($campaignIds, $defaultSubcampaignId);
        } elseif ($defaultCampaignId) {
            array_unshift($campaignIds, $defaultCampaignId);
        }

        return collect($campaignIds)->filter()->unique()->values()->all();
    }

    public function supervisorIdForImport(array $row, ?int $defaultSupervisorId, array $usernameMap = []): ?int
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
                ->where(function ($query) use ($supervisorCode, $supervisorEmail, $supervisorName, $usernameMap) {
                    if (filled($supervisorCode)) {
                        $code = trim($supervisorCode);
                        $normalizedCode = strtolower($code);

                        if (isset($usernameMap[$normalizedCode])) {
                            $query->where('username', $usernameMap[$normalizedCode]);
                        } else {
                            $fallback = $this->makeUsername($code);
                            $query->where(function ($q) use ($code, $fallback) {
                                $q->where('username', $code)
                                    ->orWhereRaw('LOWER(username) = ?', [Str::lower($code)])
                                    ->orWhere('username', $fallback);
                            });
                        }
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

    public function supervisorQuery()
    {
        return User::query()->whereHas('roles', fn ($query) => $query->where('name', 'supervisor'));
    }

    public function syncAgentCampaignAssignments(User $agent, array $campaignIds, int $supervisorId): int
    {
        $synced = 0;

        foreach ($campaignIds as $campaignId) {
            CampaignUserAssignment::updateOrCreate(
                ['campaign_id' => $campaignId, 'agent_id' => $agent->id],
                ['supervisor_id' => $supervisorId, 'is_active' => true, 'start_date' => now()->toDateString(), 'end_date' => null]
            );
            $synced++;
        }

        return $synced;
    }

    public function makeUsername(string $value): string
    {
        return Str::of($value)
            ->before('@')
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->toString() ?: 'usuario';
    }

    public function uniqueUsername(string $username, ?int $ignoreId = null): string
    {
        $base = $this->makeUsername($username);
        $candidate = $base;
        $counter = 1;

        while (User::query()->where('username', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = $base.$counter++;
        }

        return $candidate;
    }

    public function uniqueEmail(string $email, ?int $ignoreId = null): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, 'qa365.local');
        $base = $local;
        $candidate = $email;
        $counter = 1;

        while (User::query()->where('email', $candidate)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $candidate = $base.$counter++.'@'.$domain;
        }

        return $candidate;
    }

    public function generateFormulaUsername(string $name, ?string $paternal, ?string $maternal): string
    {
        $firstName = explode(' ', trim($name))[0];
        $firstLetterName = $this->cleanString(mb_substr($firstName, 0, 1));
        $cleanPaternal = $this->cleanString($paternal ?: '');

        $maternalTrimmed = trim($maternal ?: '');
        if (! empty($maternalTrimmed)) {
            $suffix = $this->cleanString(mb_substr($maternalTrimmed, 0, 1));
        } else {
            $suffix = $this->cleanString(mb_substr($paternal ?: '', 0, 1));
        }

        return $firstLetterName.$cleanPaternal.$suffix;
    }

    private function buildUsernameMap(array $rows): array
    {
        $usernameMap = [];

        foreach ($rows as $row) {
            $rowUsername = CsvImport::value($row, ['username', 'usuario', 'login', 'codigo']);
            $rowName = CsvImport::value($row, ['name', 'nombre', 'nombres']);
            $rowPaternal = CsvImport::value($row, ['paternal_surname', 'apellido_paterno']);
            $rowMaternal = CsvImport::value($row, ['maternal_surname', 'apellido_materno']);
            $rowEmail = CsvImport::value($row, ['email', 'correo', 'correo_empresa']);

            if (filled($rowUsername) && filled($rowName)) {
                $formulaUser = filled($rowPaternal)
                    ? $this->generateFormulaUsername($rowName, $rowPaternal, $rowMaternal)
                    : (strtolower(trim($rowUsername)) ?: $this->makeUsername($rowEmail ?: $rowName));

                $usernameMap[strtolower(trim($rowUsername))] = $formulaUser;
            }
        }

        return $usernameMap;
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

        return collect($campaignIds)->filter()->unique()->values()->all();
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
            ->filter(fn (Campaign $campaign) => $tokens->contains(fn ($token) => Str::lower($token) === Str::lower($campaign->displayName())))
            ->pluck('id');

        return $matchedIds->merge($matchedLabels)->unique()->values()->all();
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

    private function cleanString(string $string): string
    {
        $string = trim($string);
        $string = mb_strtolower($string, 'UTF-8');

        $utf8 = [
            '/[áàâãªä]/u' => 'a', '/[éèêë]/u' => 'e', '/[íìîï]/u' => 'i',
            '/[óòôõºö]/u' => 'o', '/[úùûü]/u' => 'u', '/[ç]/u' => 'c', '/[ñ]/u' => 'n',
        ];

        $string = preg_replace(array_keys($utf8), array_values($utf8), $string);

        return preg_replace('/[^a-z0-9]/', '', $string);
    }
}
