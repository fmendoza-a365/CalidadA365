<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
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
        'visible_to_agent_at',
        'agent_viewed_at',
        'finalized_at',
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
        'visible_to_agent_at' => 'datetime',
        'agent_viewed_at' => 'datetime',
        'finalized_at' => 'datetime',
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
        return $query->whereIn('status', ['visible_to_agent', 'agent_responded', 'disputed', 'resolved', 'final']);
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
        // 1. View All (Admin, Manager, QA Manager)
        if ($user->can('view_all_evaluations')) {
            return $query;
        }

        // 2. View Team (Supervisor, Coordinator)
        if ($user->can('view_team_evaluations')) {
            // Note: This assumes Supervisor logic. Coordinator logic requires different relationship.
            $teamAgents = \App\Models\CampaignUserAssignment::where('supervisor_id', $user->id)
                ->where('is_active', true)
                ->pluck('agent_id');
            return $query->whereIn('agent_id', $teamAgents);
        }

        // 3. View Assigned (Monitor) - Evaluations they performed
        if ($user->can('view_assigned_evaluations')) {
            return $query->where('evaluator_id', $user->id);
        }

        // 4. View Own (Agent) - Evaluations they received
        if ($user->can('view_own_evaluations')) {
            return $query->where('agent_id', $user->id)->visibleToAgent();
        }

        // Default: specific evaluation or nothing
        return $query->where('id', -1);
    }
}
