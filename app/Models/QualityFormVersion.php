<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityFormVersion extends Model
{
    protected $fillable = [
        'quality_form_id',
        'version_number',
        'status',
        'is_active',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(QualityForm::class, 'quality_form_id');
    }

    public function formAttributes(): HasMany
    {
        return $this->hasMany(QualityAttribute::class, 'form_version_id')->orderBy('sort_order');
    }

    public function attributes(): HasMany
    {
        return $this->formAttributes();
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'form_version_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function validateWeights(): array
    {
        $errors = [];

        foreach ($this->formAttributes as $attribute) {
            $nonCriticalSubs = $attribute->subAttributes->where('is_critical', false);
            $criticalSubs = $attribute->subAttributes->where('is_critical', true);

            // If ALL subs are critical (pure MP category), weight=0 is OK, skip percent check
            if ($nonCriticalSubs->isEmpty() && $criticalSubs->isNotEmpty()) {
                // Verify critical items have weight 0
                foreach ($criticalSubs as $sub) {
                    if ($sub->weight_percent > 0) {
                        $errors[] = "Atributo '{$attribute->name}': el ítem crítico '{$sub->name}' debe tener peso 0%.";
                    }
                }
                continue;
            }

            // For mixed or normal attributes: only non-critical subs must sum 100%
            $totalWeight = $nonCriticalSubs->sum('weight_percent');
            if (abs($totalWeight - 100) > 0.01) {
                $errors[] = "Atributo '{$attribute->name}': los ítems no-críticos suman {$totalWeight}%, deben sumar 100%.";
            }

            // Critical subs in a mixed attribute must have weight 0
            foreach ($criticalSubs as $sub) {
                if ($sub->weight_percent > 0) {
                    $errors[] = "Atributo '{$attribute->name}': el ítem crítico '{$sub->name}' debe tener peso 0%.";
                }
            }
        }

        return $errors;
    }
}
