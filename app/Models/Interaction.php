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
        'call_sn',
        'external_id',
        'source_type',
        'channel',
        'direction',
        'contact_reason',
        'outcome',
        'customer_reference',
        'queue_name',
        'product_name',
        'priority',
        'audio_duration',
        'transcription_status',
        'transcript_text',
        'status',
        'batch_id',
        'metadata',
        'quality_form_id',
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

    public function hasScorableQualityForm(): bool
    {
        if ($this->quality_form_id) {
            return $this->qualityForm()
                ->whereHas('versions', function ($query) {
                    $query->where('status', 'published');
                })
                ->exists();
        }

        $campaign = $this->campaign;
        if (! $campaign) {
            return false;
        }

        return (bool) $campaign->active_form_version_id
            || $campaign->forms()
                ->whereHas('versions', function ($query) {
                    $query->where('status', 'published');
                })
                ->exists();
    }

    public function scorableFormVersion(): ?QualityFormVersion
    {
        if ($this->quality_form_id) {
            return $this->qualityForm?->versions()
                ->where('status', 'published')
                ->latest('version_number')
                ->first();
        }

        $campaign = $this->campaign;
        if (! $campaign) {
            return null;
        }

        if ($campaign->activeFormVersion) {
            return $campaign->activeFormVersion;
        }

        $latestForm = $campaign->forms()
            ->whereHas('versions', function ($query) {
                $query->where('status', 'published');
            })
            ->latest()
            ->first();

        return $latestForm?->versions()
            ->where('status', 'published')
            ->latest('version_number')
            ->first();
    }

    public function scopeForUser($query, $user)
    {
        // 1. View All
        if ($user->hasAnyRole(['admin', 'qa_manager'])) {
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
                $teamAgents = \App\Models\CampaignUserAssignment::where('supervisor_id', $user->id)
                    ->where('is_active', true)
                    ->pluck('agent_id');

                $q->where('supervisor_id', $user->id);

                if ($teamAgents->isNotEmpty()) {
                    $q->orWhereIn('agent_id', $teamAgents);
                }
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
