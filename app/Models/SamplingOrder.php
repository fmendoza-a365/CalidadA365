<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SamplingOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_NOT_APPLIED = 'not_applied';

    public const STATUS_JUSTIFIED = 'justified';

    protected $fillable = [
        'sampling_plan_id',
        'order_code',
        'week_start',
        'assigned_date',
        'assigned_day',
        'advisor_code',
        'advisor_name',
        'agent_id',
        'supervisor_name',
        'supervisor_id',
        'campaign_name',
        'campaign_id',
        'quartile',
        'required_by_week',
        'rule_key',
        'rule_name',
        'rule_params',
        'instruction',
        'status',
        'evaluator_id',
        'evaluator_name',
        'interaction_id',
        'call_identifier',
        'reason',
        'comment',
        'registered_at',
    ];

    protected $casts = [
        'week_start' => 'date',
        'assigned_date' => 'date',
        'registered_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(SamplingPlan::class, 'sampling_plan_id');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function interaction()
    {
        return $this->belongsTo(Interaction::class);
    }

    public function auditEvents()
    {
        return $this->hasMany(SamplingOrderAuditEvent::class)->latest('occurred_at');
    }

    public function recordAuditEvent(string $event, ?User $actor = null, array $metadata = [], ?string $fromStatus = null, ?string $toStatus = null): SamplingOrderAuditEvent
    {
        return $this->auditEvents()->create([
            'actor_id' => $actor?->id,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => empty($metadata) ? null : $metadata,
            'occurred_at' => now(),
        ]);
    }

    public static function statusLabel(string $status): string
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_APPLIED => 'Aplicado',
            self::STATUS_NOT_APPLIED => 'No aplicado',
            self::STATUS_JUSTIFIED => 'Justificado',
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
