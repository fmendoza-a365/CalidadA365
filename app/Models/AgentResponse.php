<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentResponse extends Model
{
    protected $fillable = [
        'evaluation_id', 'agent_id', 'response_type', 
        'commitment_comment', 'dispute_reason', 'disputed_items', 'responded_at'
    ];
    
    protected $casts = [
        'disputed_items' => 'array',
        'responded_at' => 'datetime',
    ];

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function disputeResolution()
    {
        return $this->hasOne(DisputeResolution::class);
    }
}
