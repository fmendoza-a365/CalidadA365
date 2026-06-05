<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Campaign extends Model
{
    protected $fillable = [
        'parent_id',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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
        // 1. Global visibility
        if ($user->hasAnyRole(['admin', 'qa_manager'])) {
            return $query;
        }

        // 2. Manager: View only explicitly managed campaigns
        if ($user->hasRole('manager')) {
            return $query->where(function ($query) use ($user) {
                $query
                    ->whereHas('managers', function ($q) use ($user) {
                        $q->whereKey($user->id);
                    })
                    ->orWhereHas('parent.managers', function ($q) use ($user) {
                        $q->whereKey($user->id);
                    })
                    ->orWhereHas('children.managers', function ($q) use ($user) {
                        $q->whereKey($user->id);
                    });
            });
        }

        // 3. Supervisor, Agent: View only exact operational assignments.
        // Parent campaigns remain visible as containers when a child is assigned.
        if ($user->hasAnyRole(['supervisor', 'agent'])) {
            return $query->where(function ($query) use ($user) {
                $query
                    ->whereHas('assignments', function ($q) use ($user) {
                        $q->where('agent_id', $user->id)
                            ->orWhere('supervisor_id', $user->id);
                    })
                    ->orWhereHas('children.assignments', function ($q) use ($user) {
                        $q->where('agent_id', $user->id)
                            ->orWhere('supervisor_id', $user->id);
                    });
            });
        }

        // 4. QA Monitor / Coordinator: View only their assigned campaigns
        if ($user->hasAnyRole(['qa_monitor', 'qa_coordinator'])) {
            return $query->where(function ($query) use ($user) {
                $query
                    ->whereHas('managers', function ($q) use ($user) {
                        $q->whereKey($user->id);
                    })
                    ->orWhereHas('parent.managers', function ($q) use ($user) {
                        $q->whereKey($user->id);
                    })
                    ->orWhereHas('children.managers', function ($q) use ($user) {
                        $q->whereKey($user->id);
                    });
            });
        }

        // Default: No access
        return $query->whereRaw('1 = 0');
    }

    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSubcampaigns($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeOperational($query)
    {
        return $query->where(function ($query) {
            $query
                ->whereNotNull('parent_id')
                ->orWhereDoesntHave('children');
        });
    }

    public function scopeOrderedForSelect($query)
    {
        return $query
            ->with('parent')
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name');
    }

    public function isSubcampaign(): bool
    {
        return filled($this->parent_id);
    }

    public function isGeneralCampaign(): bool
    {
        return ! $this->isSubcampaign();
    }

    public function displayName(): string
    {
        return $this->parent
            ? $this->parent->name.' / '.$this->name
            : $this->name;
    }

    public static function idsWithChildren($campaignIds): array
    {
        $ids = collect($campaignIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $childIds = static::query()
            ->whereIn('parent_id', $ids)
            ->pluck('id');

        return $ids
            ->merge($childIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function idsForFilter($campaignId): array
    {
        return static::idsWithChildren([$campaignId]);
    }

    public static function visibleIdsForUser($user): array
    {
        if ($user->hasAnyRole(['admin', 'qa_manager'])) {
            return static::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($user->hasAnyRole(['manager', 'qa_monitor', 'qa_coordinator'])) {
            return static::idsWithChildren($user->managedCampaigns()->pluck('campaigns.id'));
        }

        if ($user->hasRole('supervisor')) {
            return static::idsWithParents(
                CampaignUserAssignment::query()
                    ->where('supervisor_id', $user->id)
                    ->where('is_active', true)
                    ->pluck('campaign_id')
            );
        }

        if ($user->hasRole('agent')) {
            return static::idsWithParents(
                CampaignUserAssignment::query()
                    ->where('agent_id', $user->id)
                    ->where('is_active', true)
                    ->pluck('campaign_id')
            );
        }

        return [];
    }

    public static function idsWithParents($campaignIds): array
    {
        $ids = collect($campaignIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $parentIds = static::query()
            ->whereIn('id', $ids)
            ->whereNotNull('parent_id')
            ->pluck('parent_id');

        return $ids
            ->merge($parentIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
