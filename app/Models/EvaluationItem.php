<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationItem extends Model
{
    protected $fillable = [
        'evaluation_id', 'subattribute_id', 'status', 'score', 'max_score',
        'weighted_score', 'evidence_quote', 'evidence_reference', 'confidence', 'ai_notes'
    ];
    
    protected $casts = [
        'score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'weighted_score' => 'decimal:2',
        'confidence' => 'decimal:2',
    ];

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function subAttribute()
    {
        return $this->belongsTo(QualitySubAttribute::class, 'subattribute_id');
    }

    public function scopeCompliant($query)
    {
        return $query->where('status', 'compliant');
    }

    public function scopeNonCompliant($query)
    {
        return $query->where('status', 'non_compliant');
    }
}
