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
        // 1. View All (Admin)
        if ($user->hasRole('admin')) {
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
                // 1. Evaluations by their Agents
                $teamAgents = \App\Models\CampaignUserAssignment::where('supervisor_id', $user->id)
                    ->where('is_active', true)
                    ->pluck('agent_id');
                $q->whereIn('agent_id', $teamAgents);

                // 2. Evaluations in their Campaigns (where they are supervisor)
                $campaignIds = \App\Models\CampaignUserAssignment::where('supervisor_id', $user->id)
                    ->where('is_active', true)
                    ->pluck('campaign_id');
                $q->orWhereIn('campaign_id', $campaignIds);
            });
        }

        // 5. Monitor: View Own Evaluations Only
        // "monitor solo vea la info de sus evaluaciones"
        if ($user->hasRole('qa_monitor')) {
            return $query->where('evaluator_id', $user->id);
        }

        // 6. Agent: View Own Evaluations Only
        if ($user->hasRole('agent')) {
            return $query->where('agent_id', $user->id)->visibleToAgent();
        }

        // Default: No access
        return $query->whereRaw('1 = 0');
    }
}
