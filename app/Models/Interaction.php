<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    protected $fillable = [
        'campaign_id',
        'agent_id',
        'supervisor_id',
        'occurred_at',
        'uploaded_by',
        'file_path',
        'file_name',
        'source_type',
        'audio_duration',
        'transcription_status',
        'transcript_text',
        'status',
        'batch_id',
        'metadata',
        'quality_form_id'
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function qualityForm()
    {
        return $this->belongsTo(QualityForm::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function evaluation()
    {
        // Keep this for backward compatibility, maybe prioritizing Manual or AI? 
        // For now, let's just default to AI or the latest one.
        return $this->hasOne(Evaluation::class)->latest();
    }

    public function aiEvaluation()
    {
        return $this->hasOne(Evaluation::class)->where('type', 'ai');
    }

    public function manualEvaluation()
    {
        return $this->hasOne(Evaluation::class)->where('type', 'manual');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function isAudio(): bool
    {
        return $this->source_type === 'audio';
    }

    public function isTranscribing(): bool
    {
        return $this->isAudio() && in_array($this->transcription_status, ['pending', 'processing']);
    }

    public function isTranscriptionFailed(): bool
    {
        return $this->isAudio() && $this->transcription_status === 'failed';
    }
}
