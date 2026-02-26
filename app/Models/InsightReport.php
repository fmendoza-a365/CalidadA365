<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsightReport extends Model
{
    protected $fillable = [
        'campaign_id', 'type', 'date_range_start', 'date_range_end',
        'summary_content', 'key_findings', 'generated_by'
    ];

    protected $casts = [
        'date_range_start' => 'date',
        'date_range_end' => 'date',
        'key_findings' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
