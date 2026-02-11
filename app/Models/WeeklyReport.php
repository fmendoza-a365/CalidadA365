<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyReport extends Model
{
    protected $fillable = [
        'campaign_id', 'week_start', 'week_end', 'total_evaluations', 'average_score',
        'top_failures', 'operational_insights', 'product_insights', 
        'recommendations', 'anonymized_quotes', 'generated_by', 'generated_at'
    ];
    
    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'average_score' => 'decimal:2',
        'top_failures' => 'array',
        'anonymized_quotes' => 'array',
        'generated_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
