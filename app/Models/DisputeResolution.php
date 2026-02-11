<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeResolution extends Model
{
    protected $fillable = [
        'agent_response_id', 'evaluation_id', 'resolved_by', 
        'resolution_notes', 'resolution_decision', 'adjusted_score', 'resolved_at'
    ];
    
    protected $casts = [
        'adjusted_score' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function agentResponse()
    {
        return $this->belongsTo(AgentResponse::class);
    }

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
