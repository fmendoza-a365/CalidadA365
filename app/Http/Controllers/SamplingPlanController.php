<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Interaction;
use App\Models\SamplingOrder;
use App\Models\SamplingPlan;
use App\Models\StaffingBatch;
use App\Models\StaffingMember;
use App\Services\RandomSamplingPlannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SamplingPlanController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSamplingAccess();

        $selectedCampaignIds = $this->campaignIdsForStaffingFilter($request);

        $plans = SamplingPlan::with(['campaign.parent', 'creator'])
            ->withCount([
                'orders',
                'orders as applied_orders_count' => fn ($query) => $query->where('status', SamplingOrder::STATUS_APPLIED),
                'orders as pending_orders_count' => fn ($query) => $query->where('status', SamplingOrder::STATUS_PENDING),
            ])
            ->when($request->filled('campaign_id'), fn ($query) => $query->whereIn('campaign_id', Campaign::idsForFilter($request->integer('campaign_id'))))
            ->when(!$request->filled('campaign_id') && $request->filled('parent_campaign_id'), fn ($query) => $query->whereIn('campaign_id', Campaign::idsForFilter($request->integer('parent_campaign_id'))))
            ->latest('week_start')
            ->paginate(12)
            ->withQueryString();

        $campaigns = Campaign::active()->forUser(auth()->user())->orderedForSelect()->get();
        $operationalCampaigns = Campaign::active()->forUser(auth()->user())->operational()->orderedForSelect()->get();

        $staffingBatchesForSelection = StaffingBatch::active()
            ->with(['campaign.parent', 'activeMembers.campaign.parent'])
            ->withCount(['members', 'activeMembers'])
            ->latest()
            ->get();

        $staffingBatches = StaffingBatch::active()
            ->with('campaign.parent')
            ->withCount(['members', 'activeMembers'])
            ->when(! empty($selectedCampaignIds), fn ($query) => $this->scopeStaffingBatchToCampaignIds($query, $selectedCampaignIds))
            ->latest()
            ->limit(20)
            ->get();

        $staffingBatchOptions = $staffingBatchesForSelection
            ->map(fn (StaffingBatch $batch) => $this->staffingBatchOption($batch))
            ->values();

        return view('sampling.index', compact('plans', 'campaigns', 'operationalCampaigns', 'staffingBatches', 'staffingBatchOptions'));
    }

    public function store(Request $request, RandomSamplingPlannerService $planner)
    {
        $this->authorizeSamplingManagement();

        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'week_start' => 'required|date',
            'business_days' => ['required', Rule::in(['mon-fri', 'mon-sat', 'all'])],
            'start_hour' => 'required|date_format:H:i',
            'end_hour' => 'required|date_format:H:i|after:start_hour',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'staffing_batch_id' => 'required|exists:staffing_batches,id',
            'seed' => 'nullable|string|max:120',
            'quotas.q1' => 'required|integer|min:0|max:7',
            'quotas.q2' => 'required|integer|min:0|max:7',
            'quotas.q3' => 'required|integer|min:0|max:7',
            'quotas.q4' => 'required|integer|min:0|max:7',
            'unique_day' => 'nullable|boolean',
            'rotate_methods' => 'nullable|boolean',
        ]);

        $campaign = ! empty($validated['campaign_id'])
            ? Campaign::active()->operational()->whereKey($validated['campaign_id'])->first()
            : null;

        if (! empty($validated['campaign_id']) && ! $campaign) {
            return back()
                ->withInput()
                ->withErrors(['campaign_id' => 'Selecciona una subcampaña operativa o una campaña general sin subcampañas.']);
        }

        $staffingBatch = StaffingBatch::active()
            ->with(['activeMembers.campaign.parent'])
            ->find($validated['staffing_batch_id']);

        if (! $staffingBatch) {
            return back()
                ->withInput()
                ->withErrors(['staffing_batch_id' => 'Selecciona una dotación activa.']);
        }

        if ($campaign && ! $this->staffingBatchMatchesCampaign($staffingBatch, $campaign)) {
            return back()
                ->withInput()
                ->withErrors(['staffing_batch_id' => 'La dotación seleccionada no pertenece a la subcampaña indicada.']);
        }

        $staffCsv = $this->staffCsvFromBatch($staffingBatch, $campaign);

        try {
            $plan = $planner->createPlan([
                'name' => $validated['name'] ?? null,
                'week_start' => $validated['week_start'],
                'business_days' => $validated['business_days'],
                'start_hour' => $validated['start_hour'],
                'end_hour' => $validated['end_hour'],
                'campaign_id' => $campaign?->id,
                'staffing_batch_id' => $staffingBatch?->id,
                'campaign_filter' => $campaign?->displayName(),
                'seed' => $validated['seed'] ?? null,
                'quotas' => [
                    'Q1' => $validated['quotas']['q1'],
                    'Q2' => $validated['quotas']['q2'],
                    'Q3' => $validated['quotas']['q3'],
                    'Q4' => $validated['quotas']['q4'],
                ],
                'unique_day' => $request->boolean('unique_day'),
                'rotate_methods' => $request->boolean('rotate_methods'),
                'staff_csv' => $staffCsv,
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('sampling.show', $plan)
            ->with('success', 'Plan de muestreo generado correctamente.');
    }

    public function show(Request $request, SamplingPlan $samplingPlan, RandomSamplingPlannerService $planner)
    {
        $this->authorizeSamplingAccess();

        $samplingPlan->load(['campaign.parent', 'creator', 'orders.auditEvents.actor']);
        $summary = $planner->summary($samplingPlan);

        $orders = $samplingPlan->orders()
            ->with(['agent', 'supervisor', 'campaign.parent', 'interaction', 'evaluator'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim($request->string('q')->toString());
                $query->where(function ($query) use ($term) {
                    $query
                        ->where('order_code', 'like', "%{$term}%")
                        ->orWhere('advisor_name', 'like', "%{$term}%")
                        ->orWhere('advisor_code', 'like', "%{$term}%")
                        ->orWhere('supervisor_name', 'like', "%{$term}%")
                        ->orWhere('campaign_name', 'like', "%{$term}%")
                        ->orWhere('rule_name', 'like', "%{$term}%");
                });
            })
            ->orderBy('assigned_date')
            ->orderBy('advisor_name')
            ->paginate(20)
            ->withQueryString();

        return view('sampling.show', compact('samplingPlan', 'summary', 'orders'));
    }

    public function updateOrder(Request $request, SamplingOrder $samplingOrder)
    {
        $this->authorizeSamplingManagement();

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                SamplingOrder::STATUS_APPLIED,
                SamplingOrder::STATUS_NOT_APPLIED,
                SamplingOrder::STATUS_JUSTIFIED,
                SamplingOrder::STATUS_PENDING,
            ])],
            'call_identifier' => 'nullable|string|max:150',
            'reason' => 'nullable|string|max:150',
            'comment' => 'nullable|string|max:2000',
        ]);

        if ($validated['status'] === SamplingOrder::STATUS_APPLIED && blank($validated['call_identifier'] ?? null)) {
            return back()->with('error', 'Para marcar como aplicado debes registrar el ID de llamada.');
        }

        if (in_array($validated['status'], [SamplingOrder::STATUS_NOT_APPLIED, SamplingOrder::STATUS_JUSTIFIED], true) && blank($validated['reason'] ?? null)) {
            return back()->with('error', 'Selecciona un motivo para no aplicado o justificado.');
        }

        $fromStatus = $samplingOrder->status;
        $interaction = $validated['status'] === SamplingOrder::STATUS_APPLIED
            ? $this->resolveInteraction($samplingOrder, $validated['call_identifier'] ?? null)
            : null;

        $samplingOrder->update([
            'status' => $validated['status'],
            'evaluator_id' => auth()->id(),
            'evaluator_name' => auth()->user()->name,
            'interaction_id' => $interaction?->id,
            'call_identifier' => $validated['status'] === SamplingOrder::STATUS_APPLIED ? ($validated['call_identifier'] ?? null) : null,
            'reason' => $validated['status'] === SamplingOrder::STATUS_APPLIED ? null : ($validated['reason'] ?? null),
            'comment' => $validated['comment'] ?? null,
            'registered_at' => $validated['status'] === SamplingOrder::STATUS_PENDING ? null : now(),
        ]);

        $samplingOrder->recordAuditEvent('execution_registered', auth()->user(), [
            'call_identifier' => $samplingOrder->call_identifier,
            'reason' => $samplingOrder->reason,
            'comment_present' => filled($samplingOrder->comment),
            'interaction_id' => $samplingOrder->interaction_id,
        ], $fromStatus, $samplingOrder->status);

        return back()->with('success', 'Registro de muestreo actualizado.');
    }

    public function exportOrders(SamplingPlan $samplingPlan)
    {
        $this->authorizeSamplingAccess();

        $rows = $samplingPlan->orders()
            ->with(['interaction', 'evaluator'])
            ->orderBy('assigned_date')
            ->get()
            ->map(fn (SamplingOrder $order) => [
                'orden' => $order->order_code,
                'semana' => $order->week_start?->format('Y-m-d'),
                'fecha_asignada' => $order->assigned_date?->format('Y-m-d'),
                'dia' => $order->assigned_day,
                'codigo_asesor' => $order->advisor_code,
                'asesor' => $order->advisor_name,
                'supervisor' => $order->supervisor_name,
                'campania' => $order->campaign_name,
                'cuartil' => $order->quartile,
                'regla' => $order->rule_name,
                'parametros' => $order->rule_params,
                'instruccion' => $order->instruction,
                'estado' => SamplingOrder::statusLabel($order->status),
                'evaluador' => $order->evaluator?->name ?? $order->evaluator_name,
                'id_llamada' => $order->call_identifier,
                'interaccion_id' => $order->interaction_id,
                'url_interaccion' => $order->interaction ? route('transcripts.show', $order->interaction) : null,
                'url_audio' => $order->interaction?->isAudio() ? route('transcripts.audio', $order->interaction) : null,
                'motivo' => $order->reason,
                'observacion' => $order->comment,
                'registrado' => $order->registered_at?->format('Y-m-d H:i:s'),
            ])
            ->all();

        return $this->csv('ordenes_muestreo_'.$samplingPlan->id.'.csv', $rows);
    }

    public function exportAudit(SamplingPlan $samplingPlan)
    {
        $this->authorizeSamplingAccess();

        $samplingPlan->load('orders.auditEvents.actor');

        $rows = $samplingPlan->orders
            ->flatMap(fn (SamplingOrder $order) => $order->auditEvents->map(fn ($event) => [
                'orden' => $order->order_code,
                'evento' => $event->event,
                'estado_anterior' => $event->from_status,
                'estado_nuevo' => $event->to_status,
                'usuario' => $event->actor?->name ?? 'Sistema',
                'fecha' => $event->occurred_at?->format('Y-m-d H:i:s'),
                'detalle' => json_encode($event->metadata, JSON_UNESCAPED_UNICODE),
            ]))
            ->values()
            ->all();

        return $this->csv('auditoria_muestreo_'.$samplingPlan->id.'.csv', $rows);
    }

    private function resolveInteraction(SamplingOrder $order, ?string $identifier): ?Interaction
    {
        if (blank($identifier)) {
            return null;
        }

        return Interaction::query()
            ->when($order->campaign_id, fn ($query) => $query->where('campaign_id', $order->campaign_id))
            ->when($order->agent_id, fn ($query) => $query->where('agent_id', $order->agent_id))
            ->whereDate('occurred_at', $order->assigned_date)
            ->where(function ($query) use ($identifier) {
                $query
                    ->where('call_sn', $identifier)
                    ->orWhere('external_id', $identifier);

                if (ctype_digit((string) $identifier)) {
                    $query->orWhereKey((int) $identifier);
                }
            })
            ->first();
    }

    private function csv(string $filename, array $rows)
    {
        $headers = empty($rows) ? ['sin_datos'] : array_keys($rows[0]);

        $callback = function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($header) => $row[$header] ?? '', $headers));
            }

            fclose($handle);
        };

        return Response::streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function staffCsvFromBatch(StaffingBatch $batch, ?Campaign $campaign = null): string
    {
        $lines = ['codigo,nombre,supervisor,campania,cuartil,estado'];

        $members = $batch->activeMembers;

        if ($campaign) {
            $members = $members->filter(fn (StaffingMember $member) => (int) $member->campaign_id === (int) $campaign->id);
        }

        foreach ($members as $member) {
            /** @var StaffingMember $member */
            $lines[] = collect([
                $member->employee_code,
                $member->full_name,
                $member->supervisor_name,
                $member->campaign_name,
                $member->quartile,
                'Activo',
            ])->map(fn ($value) => $this->escapeCsvValue($value))->join(',');
        }

        return implode("\n", $lines);
    }

    private function campaignIdsForStaffingFilter(Request $request): array
    {
        if ($request->filled('campaign_id')) {
            return [(int) $request->integer('campaign_id')];
        }

        if ($request->filled('parent_campaign_id')) {
            return Campaign::idsForFilter($request->integer('parent_campaign_id'));
        }

        return [];
    }

    private function scopeStaffingBatchToCampaignIds($query, array $campaignIds)
    {
        return $query->where(function ($query) use ($campaignIds) {
            $query
                ->whereIn('campaign_id', $campaignIds)
                ->orWhereHas('activeMembers', fn ($memberQuery) => $memberQuery->whereIn('campaign_id', $campaignIds));
        });
    }

    private function staffingBatchMatchesCampaign(StaffingBatch $batch, Campaign $campaign): bool
    {
        if ((int) $batch->campaign_id === (int) $campaign->id) {
            return true;
        }

        return $batch->activeMembers
            ->contains(fn (StaffingMember $member) => (int) $member->campaign_id === (int) $campaign->id);
    }

    private function staffingBatchOption(StaffingBatch $batch): array
    {
        $memberCampaignIds = $batch->activeMembers
            ->pluck('campaign_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $memberParentIds = $batch->activeMembers
            ->map(fn (StaffingMember $member) => $member->campaign?->parent_id ?: $member->campaign_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'label' => $batch->name.' · '.($batch->campaign?->displayName() ?? $batch->campaign_name ?? 'Mixta').' · '.$batch->active_members_count.' activos',
            'campaign_id' => $batch->campaign_id ? (int) $batch->campaign_id : null,
            'parent_campaign_id' => $batch->campaign
                ? (int) ($batch->campaign->parent_id ?: $batch->campaign_id)
                : null,
            'member_campaign_ids' => $memberCampaignIds->all(),
            'member_parent_campaign_ids' => $memberParentIds->all(),
        ];
    }

    private function escapeCsvValue(?string $value): string
    {
        $value = (string) $value;

        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    private function authorizeSamplingAccess(): void
    {
        if (! auth()->user()->can('view_sampling') && ! auth()->user()->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor', 'manager'])) {
            abort(403);
        }
    }

    private function authorizeSamplingManagement(): void
    {
        if (! auth()->user()->can('manage_sampling') && ! auth()->user()->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor'])) {
            abort(403);
        }
    }
}
