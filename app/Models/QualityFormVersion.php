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
            $totalWeight = $attribute->subAttributes->sum('weight_percent');
            if (abs($totalWeight - 100) > 0.01) {
                $errors[] = "Attribute '{$attribute->name}' weights sum to {$totalWeight}%, must be 100%";
            }
        }
        
        return $errors;
    }
}
