<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class QualitySubAttribute extends Model
{
    protected $table = 'quality_subattributes';
    
    protected $fillable = [
        'attribute_id', 'name', 'weight_percent', 'concept', 
        'guidelines', 'is_critical', 'sort_order'
    ];
    
    protected $casts = [
        'weight_percent' => 'decimal:2',
        'is_critical' => 'boolean',
    ];

    public function attribute()
    {
        return $this->belongsTo(QualityAttribute::class);
    }

    public function evaluationItems()
    {
        return $this->hasMany(EvaluationItem::class, 'subattribute_id');
    }

    protected function effectiveWeight(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->attribute->weight * $this->weight_percent) / 100,
        );
    }
}
