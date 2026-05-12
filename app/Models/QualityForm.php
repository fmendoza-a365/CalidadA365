<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QualityForm extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'description',
        'operational_context_markdown',
        'context_file_path',
        'context_file_original_name',
        'context_file_mime',
        'context_file_text',
        'context_file_uploaded_at',
        'context_file_uploaded_by',
        'created_by',
    ];

    protected $casts = [
        'context_file_uploaded_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function versions()
    {
        return $this->hasMany(QualityFormVersion::class);
    }

    public function latestVersion()
    {
        return $this->hasOne(QualityFormVersion::class)->latestOfMany();
    }

    public function contextUploadedBy()
    {
        return $this->belongsTo(User::class, 'context_file_uploaded_by');
    }

    public function scopeForUser($query, $user)
    {
        return $query->whereHas('campaign', function ($campaignQuery) use ($user) {
            $campaignQuery->forUser($user);
        });
    }

    public function operationalContextForPrompt(int $limit = 30000): string
    {
        $parts = [];

        if (filled($this->operational_context_markdown)) {
            $parts[] = "### Contexto operativo configurado\n" . trim($this->operational_context_markdown);
        }

        if (filled($this->context_file_text)) {
            $fileName = $this->context_file_original_name ?: 'documento adjunto';
            $parts[] = "### Documento operativo adjunto: {$fileName}\n" . trim($this->context_file_text);
        }

        if (empty($parts)) {
            return '';
        }

        return Str::limit(implode("\n\n", $parts), $limit, "\n\n[Contexto operativo truncado por longitud]");
    }
}
