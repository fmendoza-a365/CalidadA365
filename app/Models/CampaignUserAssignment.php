<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignUserAssignment extends Model
{
    protected $fillable = [
        'campaign_id', 'agent_id', 'supervisor_id', 
        'is_active', 'start_date', 'end_date'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
