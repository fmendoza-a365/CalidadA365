<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'description',
        'active_form_version_id',
        'is_active',
        'logo_path',
        'color',
        'target_quality',
        'target_aht',
        'type',
        'start_date',
        'end_date',
        'script_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'target_quality' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function getLogoUrlAttribute()
    {
        return $this->logo_path
            ? asset('storage/' . $this->logo_path)
            : null;
    }

    public function activeFormVersion(): BelongsTo
    {
        return $this->belongsTo(QualityFormVersion::class, 'active_form_version_id');
    }

    public function forms(): HasMany
    {
        return $this->hasMany(QualityForm::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CampaignUserAssignment::class);
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_user_assignments', 'campaign_id', 'agent_id')
            ->wherePivot('is_active', true);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'campaign_managers', 'campaign_id', 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, $user)
    {
        // 1. View All (Admin, Managers)
        if ($user->hasAnyRole(['admin', 'manager', 'qa_manager'])) {
            return $query;
        }

        // 2. Supervisor, Agent: View only campaigns they are assigned to
        // Supervisors and Agents use CampaignUserAssignment
        if ($user->hasAnyRole(['supervisor', 'agent'])) {
            return $query->whereHas('assignments', function ($q) use ($user) {
                $q->where('agent_id', $user->id)
                    ->orWhere('supervisor_id', $user->id);
            });
        }

        // 3. QA Monitor / Coordinator: View only their assigned (managed) campaigns
        if ($user->hasAnyRole(['qa_monitor', 'qa_coordinator'])) {
            return $query->whereHas('managers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Default: No access
        return $query->whereRaw('1 = 0');
    }
}
