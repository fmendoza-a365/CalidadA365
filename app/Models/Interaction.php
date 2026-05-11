<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    protected $fillable = [
        'campaign_id',
        'agent_id',
        'supervisor_id',
        'occurred_at',
        'uploaded_by',
        'file_path',
        'file_name',
        'source_type',
        'audio_duration',
        'transcription_status',
        'transcript_text',
        'status',
        'batch_id',
        'metadata',
        'quality_form_id'
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function qualityForm()
    {
        return $this->belongsTo(QualityForm::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function evaluation()
    {
        // Keep this for backward compatibility, maybe prioritizing Manual or AI? 
        // For now, let's just default to AI or the latest one.
        return $this->hasOne(Evaluation::class)->latest();
    }

    public function aiEvaluation()
    {
        return $this->hasOne(Evaluation::class)->where('type', 'ai');
    }

    public function manualEvaluation()
    {
        return $this->hasOne(Evaluation::class)->where('type', 'manual');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function isAudio(): bool
    {
        return $this->source_type === 'audio';
    }

    public function isTranscribing(): bool
    {
        return $this->isAudio() && in_array($this->transcription_status, ['pending', 'processing']);
    }

    public function isTranscriptionFailed(): bool
    {
        return $this->isAudio() && $this->transcription_status === 'failed';
    }

    public function scopeForUser($query, $user)
    {
        // 1. View All (Admin)
        if ($user->hasRole('admin')) {
            return $query;
        }

        // 2. Manager: View interactions from Managed Campaigns
        if ($user->hasRole('manager')) {
            $campaignIds = $user->managedCampaigns->pluck('id');
            return $query->whereIn('campaign_id', $campaignIds);
        }

        // 3. QA Manager / Coordinator: View their monitors' interactions (uploaded by them) & Assigned Campaigns
        if ($user->hasRole('qa_manager')) {
            return $query->whereIn('campaign_id', $user->managedCampaigns->pluck('id'));
        }

        if ($user->hasRole('qa_coordinator')) {
            return $query->whereIn('campaign_id', $user->managedCampaigns->pluck('id'));
        }

        // 4. Supervisor: View Agents' Interactions & Campaigns
        if ($user->hasRole('supervisor')) {
            return $query->where(function ($q) use ($user) {
                // 1. Interactions by their Agents
                $teamAgents = \App\Models\CampaignUserAssignment::where('supervisor_id', $user->id)
                    ->where('is_active', true)
                    ->pluck('agent_id');
                $q->whereIn('agent_id', $teamAgents);

                // 2. Interactions in their Campaigns (where they are supervisor)
                $campaignIds = \App\Models\CampaignUserAssignment::where('supervisor_id', $user->id)
                    ->where('is_active', true)
                    ->pluck('campaign_id');
                $q->orWhereIn('campaign_id', $campaignIds);
            });
        }

        // 5. Monitor: View interactions in campaigns they are assigned to
        if ($user->hasRole('qa_monitor')) {
            return $query->whereIn('campaign_id', $user->managedCampaigns->pluck('id'));
        }

        // 6. Agent: View Own Interactions Only
        if ($user->hasRole('agent')) {
            return $query->where('agent_id', $user->id);
        }

        // Default: No access
        return $query->whereRaw('1 = 0');
    }
}
