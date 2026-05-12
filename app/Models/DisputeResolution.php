<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeResolution extends Model
{
    public const STATUS_PENDING_SUPERVISOR_REVIEW = 'pending_supervisor_review';
    public const STATUS_PENDING_QA_REVIEW = 'pending_qa_review';
    public const STATUS_PENDING_COORDINATOR_REVIEW = 'pending_coordinator_review';
    public const STATUS_READY_MANAGER_RESOLUTION = 'ready_manager_resolution';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'agent_response_id',
        'evaluation_id',
        'status',
        'supervisor_reviewed_by',
        'supervisor_reviewed_at',
        'supervisor_notes',
        'qa_reviewed_by',
        'qa_reviewed_at',
        'qa_recommendation',
        'qa_notes',
        'coordinator_reviewed_by',
        'coordinator_reviewed_at',
        'coordinator_decision',
        'coordinator_notes',
        'resolved_by',
        'resolution_notes',
        'resolution_decision',
        'adjusted_score',
        'resolved_at',
    ];
    
    protected $casts = [
        'adjusted_score' => 'decimal:2',
        'supervisor_reviewed_at' => 'datetime',
        'qa_reviewed_at' => 'datetime',
        'coordinator_reviewed_at' => 'datetime',
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

    public function supervisorReviewer()
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    public function qaReviewer()
    {
        return $this->belongsTo(User::class, 'qa_reviewed_by');
    }

    public function coordinatorReviewer()
    {
        return $this->belongsTo(User::class, 'coordinator_reviewed_by');
    }

    public function isResolved(): bool
    {
        return filled($this->resolved_at) || $this->status === self::STATUS_RESOLVED;
    }

    public static function statusLabel(?string $status): string
    {
        return [
            self::STATUS_PENDING_SUPERVISOR_REVIEW => 'Pendiente supervisor',
            self::STATUS_PENDING_QA_REVIEW => 'Pendiente QA Monitor',
            self::STATUS_PENDING_COORDINATOR_REVIEW => 'Pendiente coordinador QA',
            self::STATUS_READY_MANAGER_RESOLUTION => 'Pendiente resolución QA Manager',
            self::STATUS_RESOLVED => 'Resuelta',
        ][$status] ?? 'Pendiente revisión';
    }
}
