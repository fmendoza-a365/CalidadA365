<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationAuditEvent extends Model
{
    protected $fillable = [
        'evaluation_id',
        'actor_id',
        'event',
        'from_status',
        'to_status',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function eventLabel(string $event): string
    {
        return [
            'ai_queued' => 'IA enviada a cola',
            'ai_processing_started' => 'IA inició procesamiento',
            'ai_evaluated' => 'IA generó evaluación',
            'ai_failed' => 'IA falló',
            'reanalyze_requested' => 'Reanálisis solicitado',
            'published' => 'Publicada al asesor',
            'manual_created' => 'Evaluación manual creada',
            'gold_marked' => 'Marcada como Golden Record',
            'gold_unmarked' => 'Desmarcada como Golden Record',
            'agent_accepted' => 'Aceptada por asesor',
            'agent_disputed' => 'Disputada por asesor',
            'dispute_supervisor_reviewed' => 'Revisada por supervisor',
            'dispute_qa_reviewed' => 'Revisada por QA',
            'dispute_coordinator_reviewed' => 'Validada por coordinador',
            'dispute_resolved' => 'Disputa resuelta',
            'closed' => 'Evaluación cerrada',
            'reopened' => 'Evaluación reabierta',
            'legacy_status_normalized' => 'Estado legacy normalizado',
        ][$event] ?? ucfirst(str_replace('_', ' ', $event));
    }
}
