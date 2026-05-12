<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    public const STATUS_PENDING_AI = 'pending_ai';
    public const STATUS_AI_PROCESSING = 'ai_processing';
    public const STATUS_PENDING_MONITOR_REVIEW = 'pending_monitor_review';
    public const STATUS_AI_REANALYSIS_REQUESTED = 'ai_reanalysis_requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED_TO_AGENT = 'published_to_agent';
    public const STATUS_AGENT_ACCEPTED = 'agent_accepted';
    public const STATUS_AGENT_DISPUTED = 'agent_disputed';
    public const STATUS_DISPUTE_RESOLVED = 'dispute_resolved';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'interaction_id',
        'form_version_id',
        'campaign_id',
        'agent_id',
        'type',
        'evaluator_id',
        'total_score',
        'max_possible_score',
        'percentage_score',
        'status',
        'is_gold',
        'ai_processed_at',
        'ai_model',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'published_by',
        'visible_to_agent_at',
        'agent_viewed_at',
        'finalized_at',
        'reanalysis_requested_at',
        'reanalysis_requested_by',
        'ai_prompt',
        'ai_raw_response',
        'ai_summary',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'max_possible_score' => 'decimal:2',
        'percentage_score' => 'decimal:2',
        'is_gold' => 'boolean',
        'ai_processed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'visible_to_agent_at' => 'datetime',
        'agent_viewed_at' => 'datetime',
        'finalized_at' => 'datetime',
        'reanalysis_requested_at' => 'datetime',
    ];

    public function scopeGold($query)
    {
        return $query->where('is_gold', true);
    }

    public function interaction()
    {
        return $this->belongsTo(Interaction::class);
    }

    public function formVersion()
    {
        return $this->belongsTo(QualityFormVersion::class, 'form_version_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function items()
    {
        return $this->hasMany(EvaluationItem::class);
    }

    public function agentResponse()
    {
        return $this->hasOne(AgentResponse::class);
    }

    public function dispute()
    {
        return $this->hasOne(DisputeResolution::class);
    }

    public function scopeVisibleToAgent($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PUBLISHED_TO_AGENT,
            self::STATUS_AGENT_ACCEPTED,
            self::STATUS_AGENT_DISPUTED,
            self::STATUS_DISPUTE_RESOLVED,
            self::STATUS_CLOSED,
            'visible_to_agent',
            'agent_responded',
            'disputed',
            'resolved',
            'final',
        ]);
    }

    public function scopeAi($query)
    {
        return $query->where('type', 'ai');
    }

    public function scopeManual($query)
    {
        return $query->where('type', 'manual');
    }
    public function scopeForUser($query, $user)
    {
        // 1. View All
        if ($user->hasAnyRole(['admin', 'qa_manager'])) {
            return $query;
        }

        // 2. Manager: View Managed Campaigns
        if ($user->hasRole('manager')) {
            $campaignIds = $user->managedCampaigns->pluck('id');
            return $query->whereIn('campaign_id', $campaignIds);
        }

        // 3. QA Manager / Coordinator: View Monitors' Evaluations & Assigned Campaigns
        // "qa manager vea el de sus monitores y campañas"
        if ($user->hasRole('qa_manager') || $user->hasRole('qa_coordinator')) {
            return $query->where(function ($q) use ($user) {
                // Defines "Sus Monitores"
                $monitorIds = $user->monitors->pluck('id');
                if ($monitorIds->isNotEmpty()) {
                    $q->whereIn('evaluator_id', $monitorIds);
                }

                // Defines "Sus Campañas" (direct assignment if any, or implied by team)
                // Assuming QA Managers might also have campaign_managers entries or similar.
                // For now, if they are assigned to a campaign via pivot (unlikely for QA but possible) check there.
                // Or if they are supervisors in a campaign.
                $q->orWhereIn('campaign_id', $user->managedCampaigns->pluck('id'));
            });
        }

        // 4. Supervisor: View Agents' Evaluations & Campaigns
        // "supervisor el de sus agente y campañas"
        if ($user->hasRole('supervisor')) {
            return $query->where(function ($q) use ($user) {
                $teamAgents = \App\Models\CampaignUserAssignment::where('supervisor_id', $user->id)
                    ->where('is_active', true)
                    ->pluck('agent_id');

                $q->whereHas('interaction', function ($interactionQuery) use ($user) {
                    $interactionQuery->where('supervisor_id', $user->id);
                });

                if ($teamAgents->isNotEmpty()) {
                    $q->orWhereIn('agent_id', $teamAgents);
                }
            });
        }

        // 5. Monitor: View Own Evaluations Only
        if ($user->hasRole('qa_monitor')) {
            return $query->where(function ($q) use ($user) {
                $q->where('evaluator_id', $user->id)
                    ->orWhereIn('campaign_id', $user->managedCampaigns->pluck('id'));
            });
        }

        // 6. Agent: View Own Evaluations Only
        if ($user->hasRole('agent')) {
            return $query->where('agent_id', $user->id)->visibleToAgent();
        }

        // Default: No access
        return $query->whereRaw('1 = 0');
    }

    public function isVisibleToAgent(): bool
    {
        return in_array($this->status, [
            self::STATUS_PUBLISHED_TO_AGENT,
            self::STATUS_AGENT_ACCEPTED,
            self::STATUS_AGENT_DISPUTED,
            self::STATUS_DISPUTE_RESOLVED,
            self::STATUS_CLOSED,
            'visible_to_agent',
            'agent_responded',
            'disputed',
            'resolved',
            'final',
        ], true);
    }

    public function isPendingMonitorReview(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_MONITOR_REVIEW,
            self::STATUS_AI_REANALYSIS_REQUESTED,
            'ai_done',
        ], true);
    }

    public function canBePublished(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_MONITOR_REVIEW,
            self::STATUS_APPROVED,
            'ai_done',
        ], true);
    }

    public static function statusLabel(string $status): string
    {
        return [
            self::STATUS_PENDING_AI => 'Pendiente IA',
            self::STATUS_AI_PROCESSING => 'Procesando IA',
            self::STATUS_PENDING_MONITOR_REVIEW => 'Pendiente revision monitor',
            self::STATUS_AI_REANALYSIS_REQUESTED => 'Reanalisis solicitado',
            self::STATUS_APPROVED => 'Aprobada',
            self::STATUS_PUBLISHED_TO_AGENT => 'Publicada al asesor',
            self::STATUS_AGENT_ACCEPTED => 'Aceptada por asesor',
            self::STATUS_AGENT_DISPUTED => 'Disputada por asesor',
            self::STATUS_DISPUTE_RESOLVED => 'Disputa resuelta',
            self::STATUS_CLOSED => 'Cerrada',
            'visible_to_agent' => 'Pendiente firma',
            'agent_responded' => 'Firmada',
            'disputed' => 'En disputa',
            'resolved' => 'Resuelta',
            'final' => 'Final',
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
