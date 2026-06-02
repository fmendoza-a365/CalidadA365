<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\SamplingOrder;
use App\Models\SamplingPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class RandomSamplingPlannerService
{
    private const DAY_LABELS = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    public function createPlan(array $config, User $actor): SamplingPlan
    {
        $staff = $this->staffFromCsv($config['staff_csv'] ?? '', $config['campaign_filter'] ?? null);

        if ($staff->isEmpty()) {
            throw new InvalidArgumentException('No hay asesores activos válidos en la dotación.');
        }

        $weekStart = CarbonImmutable::parse($config['week_start'])->startOfDay();
        $weekDates = $this->weekDates($weekStart, $config['business_days'] ?? 'mon-fri');

        if (empty($weekDates)) {
            throw new InvalidArgumentException('No hay días disponibles para generar órdenes.');
        }

        $quotas = $this->normalizeQuotas($config['quotas'] ?? []);
        $seed = filled($config['seed'] ?? null)
            ? (string) $config['seed']
            : $weekStart->format('Ymd').'-'.substr(hash('sha256', $actor->id.'|'.microtime(true)), 0, 10);

        return DB::transaction(function () use ($config, $actor, $staff, $weekStart, $weekDates, $quotas, $seed) {
            $plan = SamplingPlan::create([
                'name' => $config['name'] ?? null,
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekStart->addDays(6)->toDateString(),
                'business_days' => $config['business_days'] ?? 'mon-fri',
                'start_hour' => $config['start_hour'] ?? '09:00',
                'end_hour' => $config['end_hour'] ?? '18:00',
                'campaign_id' => $config['campaign_id'] ?? null,
                'staffing_batch_id' => $config['staffing_batch_id'] ?? null,
                'campaign_filter' => $config['campaign_filter'] ?? null,
                'seed' => $seed,
                'quotas' => $quotas,
                'unique_day' => (bool) ($config['unique_day'] ?? true),
                'rotate_methods' => (bool) ($config['rotate_methods'] ?? true),
                'staff_count' => $staff->count(),
                'orders_count' => 0,
                'staff_csv' => $config['staff_csv'] ?? null,
                'created_by' => $actor->id,
            ]);

            $random = $this->seededRandom($seed);
            $methodCursor = 0;
            $ordersCount = 0;

            foreach ($staff as $person) {
                $quartile = strtoupper((string) $person['cuartil']);
                $required = $quotas[$quartile] ?? 0;

                if ($required <= 0) {
                    continue;
                }

                $selectedDays = $this->selectDays($weekDates, $required, (bool) $plan->unique_day, $random);

                foreach ($selectedDays as $index => $dayInfo) {
                    $method = $this->selectMethod((bool) $plan->rotate_methods, $methodCursor, $random);
                    $built = $this->buildMethod($method['id'], $random, [
                        'start_hour' => $plan->start_hour,
                        'end_hour' => $plan->end_hour,
                    ]);
                    $campaign = $this->resolveCampaign($person['campania'] ?? null, $plan->campaign_id);
                    $agent = $this->resolveUser($person['codigo'] ?? null, $person['nombre'] ?? null, 'agent');
                    $supervisor = $this->resolveUser(null, $person['supervisor'] ?? null, 'supervisor');

                    $order = $plan->orders()->create([
                        'order_code' => $this->buildOrderCode($plan->id, $weekStart->toDateString(), $person['codigo'], $index + 1),
                        'week_start' => $weekStart->toDateString(),
                        'assigned_date' => $dayInfo['ymd'],
                        'assigned_day' => $dayInfo['label'],
                        'advisor_code' => $person['codigo'],
                        'advisor_name' => $person['nombre'],
                        'agent_id' => $agent?->id,
                        'supervisor_name' => $person['supervisor'] ?? null,
                        'supervisor_id' => $supervisor?->id,
                        'campaign_name' => $person['campania'] ?? null,
                        'campaign_id' => $campaign?->id,
                        'quartile' => $quartile,
                        'required_by_week' => $required,
                        'rule_key' => $method['id'],
                        'rule_name' => $built['rule'],
                        'rule_params' => $built['params'],
                        'instruction' => $built['instruction'],
                        'status' => SamplingOrder::STATUS_PENDING,
                    ]);

                    $order->recordAuditEvent('order_generated', $actor, [
                        'quartile' => $quartile,
                        'assigned_date' => $dayInfo['ymd'],
                        'rule' => $built['rule'],
                        'params' => $built['params'],
                        'seed' => $seed,
                    ], null, SamplingOrder::STATUS_PENDING);

                    $ordersCount++;
                }
            }

            $plan->update(['orders_count' => $ordersCount]);

            return $plan->fresh(['orders']);
        });
    }

    public function summary(SamplingPlan $plan): array
    {
        $orders = $plan->orders;
        $byAdvisor = $orders->groupBy('advisor_code')->map(function (Collection $advisorOrders) {
            $first = $advisorOrders->first();
            $required = (int) $first->required_by_week;
            $applied = $advisorOrders->where('status', SamplingOrder::STATUS_APPLIED)->count();
            $justified = $advisorOrders->where('status', SamplingOrder::STATUS_JUSTIFIED)->count();
            $status = $applied >= $required
                ? 'Cumple'
                : (($applied + $justified) >= $required ? 'Cumple con justificación' : 'Pendiente');

            return [
                'advisor_code' => $first->advisor_code,
                'advisor_name' => $first->advisor_name,
                'supervisor' => $first->supervisor_name,
                'campaign' => $first->campaign_name,
                'quartile' => $first->quartile,
                'required' => $required,
                'generated' => $advisorOrders->count(),
                'applied' => $applied,
                'not_applied' => $advisorOrders->where('status', SamplingOrder::STATUS_NOT_APPLIED)->count(),
                'justified' => $justified,
                'pending' => $advisorOrders->where('status', SamplingOrder::STATUS_PENDING)->count(),
                'status' => $status,
            ];
        })->values();

        return [
            'agents' => $byAdvisor->count(),
            'orders' => $orders->count(),
            'applied' => $orders->where('status', SamplingOrder::STATUS_APPLIED)->count(),
            'pending' => $orders->where('status', SamplingOrder::STATUS_PENDING)->count(),
            'not_applied' => $orders->where('status', SamplingOrder::STATUS_NOT_APPLIED)->count(),
            'justified' => $orders->where('status', SamplingOrder::STATUS_JUSTIFIED)->count(),
            'rows' => $byAdvisor->all(),
        ];
    }

    public function methods(): array
    {
        return [
            ['id' => 'call_n', 'label' => 'Evaluar la llamada número N del día'],
            ['id' => 'after_hour', 'label' => 'Primera llamada después de una hora aleatoria'],
            ['id' => 'n_plus_x', 'label' => 'Desde la llamada N, contar X posteriores'],
            ['id' => 'near_hour', 'label' => 'Llamada más cercana a una hora aleatoria'],
            ['id' => 'block', 'label' => 'Llamada dentro de bloque horario'],
            ['id' => 'event_after', 'label' => 'Llamada posterior a evento operativo'],
            ['id' => 'percentage', 'label' => 'Llamada por porcentaje del avance del día'],
        ];
    }

    public function staffFromCsv(string $text, ?string $campaignFilter = null): Collection
    {
        $rows = $this->parseCsv($text);
        $campaignFilter = $campaignFilter ? Str::lower(trim($campaignFilter)) : null;

        return collect($rows)
            ->map(function (array $row) {
                return [
                    'codigo' => trim((string) ($row['codigo'] ?? $row['code'] ?? '')),
                    'nombre' => trim((string) ($row['nombre'] ?? $row['name'] ?? '')),
                    'supervisor' => trim((string) ($row['supervisor'] ?? '')),
                    'campania' => trim((string) ($row['campania'] ?? $row['campana'] ?? $row['campaign'] ?? '')),
                    'cuartil' => strtoupper(trim((string) ($row['cuartil'] ?? $row['quartile'] ?? ''))),
                    'estado' => trim((string) ($row['estado'] ?? $row['status'] ?? '')),
                ];
            })
            ->filter(fn (array $row) => $row['codigo'] !== '' && $row['nombre'] !== '')
            ->filter(fn (array $row) => Str::lower($row['estado']) === 'activo')
            ->filter(fn (array $row) => in_array($row['cuartil'], ['Q1', 'Q2', 'Q3', 'Q4'], true))
            ->filter(fn (array $row) => ! $campaignFilter || Str::lower($row['campania']) === $campaignFilter)
            ->values();
    }

    private function parseCsv(string $text): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || ! collect($data)->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty()) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn ($header) => $this->normalizeKey((string) $header), $data);
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $data[$index] ?? '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeKey(string $key): string
    {
        return Str::of($key)
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->toString();
    }

    private function normalizeQuotas(array $quotas): array
    {
        return [
            'Q1' => max(0, (int) ($quotas['Q1'] ?? $quotas['q1'] ?? 1)),
            'Q2' => max(0, (int) ($quotas['Q2'] ?? $quotas['q2'] ?? 2)),
            'Q3' => max(0, (int) ($quotas['Q3'] ?? $quotas['q3'] ?? 3)),
            'Q4' => max(0, (int) ($quotas['Q4'] ?? $quotas['q4'] ?? 4)),
        ];
    }

    private function weekDates(CarbonImmutable $start, string $mode): array
    {
        $dates = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $start->addDays($i);
            $day = (int) $date->dayOfWeek;
            $isValid = $mode === 'all'
                || ($mode === 'mon-fri' && $day >= 1 && $day <= 5)
                || ($mode === 'mon-sat' && $day >= 1 && $day <= 6);

            if ($isValid) {
                $dates[] = [
                    'ymd' => $date->toDateString(),
                    'day' => $day,
                    'label' => self::DAY_LABELS[$day],
                ];
            }
        }

        return $dates;
    }

    private function selectDays(array $weekDates, int $required, bool $uniqueDay, callable $random): array
    {
        if ($required <= 0) {
            return [];
        }

        if ($uniqueDay) {
            return array_slice($this->shuffle($weekDates, $random), 0, min($required, count($weekDates)));
        }

        $days = [];
        for ($i = 0; $i < $required; $i++) {
            $days[] = $weekDates[$this->randomInt($random, 0, count($weekDates) - 1)];
        }

        return $days;
    }

    private function selectMethod(bool $rotateMethods, int &$methodCursor, callable $random): array
    {
        $methods = $this->methods();

        if ($rotateMethods) {
            return $methods[$methodCursor++ % count($methods)];
        }

        return $methods[$this->randomInt($random, 0, count($methods) - 1)];
    }

    private function buildMethod(string $method, callable $random, array $config): array
    {
        return match ($method) {
            'call_n' => $this->methodCallN($random),
            'after_hour' => $this->methodAfterHour($random, $config),
            'n_plus_x' => $this->methodNPlusX($random),
            'near_hour' => $this->methodNearHour($random, $config),
            'block' => $this->methodBlock($random),
            'event_after' => $this->methodEventAfter($random),
            'percentage' => $this->methodPercentage($random),
            default => $this->methodCallN($random),
        };
    }

    private function methodCallN(callable $random): array
    {
        $n = $this->randomInt($random, 3, 12);

        return [
            'rule' => 'Llamada número N del día',
            'params' => "N={$n}",
            'instruction' => "Evaluar la llamada número {$n} del asesor en el día asignado.",
        ];
    }

    private function methodAfterHour(callable $random, array $config): array
    {
        $start = $this->timeToMinutes($config['start_hour'] ?? '09:00');
        $end = max($start + 60, $this->timeToMinutes($config['end_hour'] ?? '18:00') - 45);
        $hour = $this->minutesToTime($this->randomInt($random, $start, $end));

        return [
            'rule' => 'Primera llamada después de hora aleatoria',
            'params' => "Hora={$hour}",
            'instruction' => "Evaluar la primera llamada válida después de las {$hour}.",
        ];
    }

    private function methodNPlusX(callable $random): array
    {
        $base = $this->randomInt($random, 2, 5);
        $jump = $this->randomInt($random, 3, 8);

        return [
            'rule' => 'Llamada N + X posteriores',
            'params' => "Base={$base}; posteriores={$jump}",
            'instruction' => "Desde la llamada número {$base} del día, contar {$jump} llamadas posteriores y evaluar la resultante.",
        ];
    }

    private function methodNearHour(callable $random, array $config): array
    {
        $start = $this->timeToMinutes($config['start_hour'] ?? '09:00');
        $end = $this->timeToMinutes($config['end_hour'] ?? '18:00');
        $hour = $this->minutesToTime($this->randomInt($random, $start, $end));

        return [
            'rule' => 'Llamada más cercana a hora aleatoria',
            'params' => "Hora objetivo={$hour}",
            'instruction' => "Evaluar la llamada más cercana a las {$hour}.",
        ];
    }

    private function methodBlock(callable $random): array
    {
        $blocks = ['primer bloque del turno', 'bloque de media mañana', 'bloque posterior al almuerzo', 'último bloque del turno'];
        $block = $blocks[$this->randomInt($random, 0, count($blocks) - 1)];

        return [
            'rule' => 'Llamada dentro de bloque horario',
            'params' => "Bloque={$block}",
            'instruction' => "Evaluar una llamada del {$block}.",
        ];
    }

    private function methodEventAfter(callable $random): array
    {
        $events = ['inicio de turno', 'break', 'almuerzo', 'cambio de campaña', 'reunión corta'];
        $event = $events[$this->randomInt($random, 0, count($events) - 1)];
        $position = $this->randomInt($random, 1, 3);

        return [
            'rule' => 'Llamada posterior a evento operativo',
            'params' => "Evento={$event}; posición posterior={$position}",
            'instruction' => "Evaluar la llamada número {$position} después de {$event}.",
        ];
    }

    private function methodPercentage(callable $random): array
    {
        $percentage = $this->randomInt($random, 20, 80);

        return [
            'rule' => 'Llamada por porcentaje del día',
            'params' => "Porcentaje={$percentage}%",
            'instruction' => "Evaluar la llamada ubicada aproximadamente al {$percentage}% del avance del día.",
        ];
    }

    private function resolveCampaign(?string $campaignName, ?int $forcedCampaignId = null): ?Campaign
    {
        if ($forcedCampaignId) {
            return Campaign::find($forcedCampaignId);
        }

        if (! filled($campaignName)) {
            return null;
        }

        return Campaign::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($campaignName))])
            ->first();
    }

    private function resolveUser(?string $code, ?string $name, ?string $role = null): ?User
    {
        if (blank($code) && blank($name)) {
            return null;
        }

        $query = User::query();

        if ($role && Role::where('name', $role)->exists()) {
            $query->role($role);
        }

        $query->where(function ($query) use ($code, $name) {
            if (filled($code)) {
                $query->where('username', $code)
                    ->orWhere('email', $code);
            }

            if (filled($name)) {
                $query->orWhereRaw('LOWER(name) = ?', [Str::lower(trim($name))]);
            }
        });

        return $query->first();
    }

    private function buildOrderCode(int $planId, string $weekStart, string $advisorCode, int $index): string
    {
        $cleanDate = str_replace('-', '', $weekStart);
        $cleanCode = Str::of($advisorCode)->upper()->replaceMatches('/[^A-Z0-9]/', '')->toString() ?: 'SINCODE';

        return 'ORD-'.$cleanDate.'-'.$cleanCode.'-P'.$planId.'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    }

    private function seededRandom(string $seedText): callable
    {
        $seed = 0;
        foreach (mb_str_split($seedText) as $char) {
            $seed = (($seed * 31) + mb_ord($char)) & 0xffffffff;
        }

        return function () use (&$seed): float {
            $seed = ((1664525 * $seed) + 1013904223) & 0xffffffff;

            return $seed / 4294967296;
        };
    }

    private function randomInt(callable $random, int $min, int $max): int
    {
        if ($max < $min) {
            return $min;
        }

        return (int) floor($random() * ($max - $min + 1)) + $min;
    }

    private function shuffle(array $array, callable $random): array
    {
        $copy = array_values($array);
        for ($i = count($copy) - 1; $i > 0; $i--) {
            $j = (int) floor($random() * ($i + 1));
            [$copy[$i], $copy[$j]] = [$copy[$j], $copy[$i]];
        }

        return $copy;
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time), 2, 0);

        return ((int) $hours * 60) + (int) $minutes;
    }

    private function minutesToTime(int $total): string
    {
        $hours = str_pad((string) floor($total / 60), 2, '0', STR_PAD_LEFT);
        $minutes = str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT);

        return "{$hours}:{$minutes}";
    }
}
