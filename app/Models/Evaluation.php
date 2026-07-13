<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Evaluation extends Model
{
    public const STATUS_PENDING_AI = 'pending_ai';

    public const STATUS_AI_PROCESSING = 'ai_processing';

    public const STATUS_AI_FAILED = 'ai_failed';

    public const STATUS_PENDING_MONITOR_REVIEW = 'pending_monitor_review';

    public const STATUS_AI_REANALYSIS_REQUESTED = 'ai_reanalysis_requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PUBLISHED_TO_AGENT = 'published_to_agent';

    public const STATUS_AGENT_ACCEPTED = 'agent_accepted';

    public const STATUS_AGENT_DISPUTED = 'agent_disputed';

    public const STATUS_DISPUTE_RESOLVED = 'dispute_resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_AGENT_REVIEWED = 'agent_reviewed';

    public const STATUS_COMMITMENT_REGISTERED = 'commitment_registered';

    protected $fillable = [
        'interaction_id',
        'form_version_id',
        'campaign_id',
        'agent_id',
        'type',
        'evaluator_id',
        'total_score',
        'max_possible_score',
        'percentage_score',
        'status',
        'previous_status_before_close',
        'closed_at',
        'closed_by',
        'closure_reason',
        'reopened_at',
        'reopened_by',
        'is_gold',
        'ai_processed_at',
        'ai_model',
        'ai_provider',
        'ai_prompt_version',
        'ai_prompt_hash',
        'ai_settings_snapshot',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'review_claimed_by',
        'review_claimed_at',
        'review_claim_expires_at',
        'published_by',
        'visible_to_agent_at',
        'agent_viewed_at',
        'finalized_at',
        'reanalysis_requested_at',
        'reanalysis_requested_by',
        'ai_prompt',
        'ai_raw_response',
        'ai_summary',
        'ai_feedback',
        'feedback_audio_path',
        'feedback_audio_disk',
        'feedback_audio_generated_at',
        'feedback_audio_status',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'max_possible_score' => 'decimal:2',
        'percentage_score' => 'decimal:2',
        'is_gold' => 'boolean',
        'ai_processed_at' => 'datetime',
        'ai_settings_snapshot' => 'array',
        'ai_feedback' => 'array',
        'feedback_audio_generated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'review_claimed_at' => 'datetime',
        'review_claim_expires_at' => 'datetime',
        'visible_to_agent_at' => 'datetime',
        'agent_viewed_at' => 'datetime',
        'finalized_at' => 'datetime',
        'reanalysis_requested_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public const AI_FEEDBACK_SECTIONS = [
        'performanceSummary' => 'Resumen del desempeño',
        'productKnowledge' => 'Conocimiento del producto',
        'emotionalHandlingAndEmpathy' => 'Manejo de emociones y empatía',
        'strengths' => 'Fortalezas',
        'improvementOpportunities' => 'Oportunidades de mejora',
    ];

    public function structuredAiFeedback(): array
    {
        $feedback = is_array($this->ai_feedback) ? $this->ai_feedback : [];
        $legacy = $this->parseLegacyAiSummary($this->ai_summary);

        return collect(self::AI_FEEDBACK_SECTIONS)
            ->map(function (string $title, string $key) use ($feedback, $legacy) {
                $content = trim((string) ($feedback[$key] ?? $legacy[$key] ?? ''));

                return [
                    'key' => $key,
                    'title' => $title,
                    'content' => $content !== ''
                        ? $content
                        : 'Sin contenido específico para esta sección.',
                ];
            })
            ->values()
            ->all();
    }

    public function structuredAiFeedbackForPrompt(): array
    {
        return collect($this->structuredAiFeedback())
            ->mapWithKeys(fn (array $section) => [$section['key'] => $section['content']])
            ->all();
    }

    private function parseLegacyAiSummary(?string $summary): array
    {
        $summary = trim((string) $summary);
        if ($summary === '') {
            return [];
        }

        $aliases = [
            'performanceSummary' => [
                'resumen',
                'resumen general',
                'resumen del desempeño',
                'desempeño',
            ],
            'productKnowledge' => [
                'conocimiento del producto',
                'conocimiento de producto',
                'producto',
            ],
            'emotionalHandlingAndEmpathy' => [
                'manejo emocional',
                'manejo emocional y empatía',
                'manejo de emociones y empatía',
                'emociones y empatía',
                'manejo de emociones',
            ],
            'strengths' => [
                'fortalezas',
                'fortalezas detectadas',
            ],
            'improvementOpportunities' => [
                'oportunidades',
                'oportunidades de mejora',
                'mejoras',
            ],
        ];

        $markers = [];
        foreach ($aliases as $key => $labels) {
            foreach ($labels as $label) {
                $regex = '/(?<=^|\s)(?:#{1,4}\s*)?(?:[^\w\s]+\s*)?'.preg_quote($label, '/').'(?:\s*[^\w\s]+)*\s*(?::|\R|$)/iu';
                if (preg_match_all($regex, $summary, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $markers[] = [
                            'key' => $key,
                            'label' => $label,
                            'start' => $match[1],
                            'end' => $match[1] + strlen($match[0]),
                        ];
                    }
                }
            }
        }

        $markers = collect($markers)
            ->sortBy('start')
            ->unique(fn (array $marker) => $marker['start'])
            ->values();

        if ($markers->isEmpty()) {
            return ['performanceSummary' => $summary];
        }

        $sections = [];
        foreach ($markers as $index => $marker) {
            $next = $markers[$index + 1] ?? null;
            $content = substr(
                $summary,
                $marker['end'],
                $next ? $next['start'] - $marker['end'] : null
            );

            $sections[$marker['key']] = trim((string) $content);
        }

        return $sections;
    }

    public function scopeGold($query)
    {
        return $query->where('is_gold', true);
    }

    public static function createPendingAiForInteraction(Interaction $interaction, QualityFormVersion $formVersion, ?User $actor = null, array $metadata = []): self
    {
        $evaluation = self::create([
            'interaction_id' => $interaction->id,
            'form_version_id' => $formVersion->id,
            'campaign_id' => $interaction->campaign_id,
            'agent_id' => $interaction->agent_id,
            'type' => 'ai',
            'evaluator_id' => null,
            'total_score' => null,
            'max_possible_score' => 0,
            'percentage_score' => null,
            'status' => self::STATUS_PENDING_AI,
        ]);

        $evaluation->recordAuditEvent(
            'ai_queued',
            $actor,
            $metadata,
            null,
            self::STATUS_PENDING_AI
        );

        return $evaluation;
    }

    public function interaction()
    {
        return $this->belongsTo(Interaction::class);
    }

    public function formVersion()
    {
        return $this->belongsTo(QualityFormVersion::class, 'form_version_id');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewClaimer()
    {
        return $this->belongsTo(User::class, 'review_claimed_by');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopener()
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    public function items()
    {
        return $this->hasMany(EvaluationItem::class);
    }

    public function agentResponse()
    {
        return $this->hasOne(AgentResponse::class);
    }

    public function dispute()
    {
        return $this->hasOne(DisputeResolution::class);
    }

    public function auditEvents()
    {
        return $this->hasMany(EvaluationAuditEvent::class)->latest('occurred_at');
    }

    public function recordAuditEvent(
        string $event,
        ?User $actor = null,
        array $metadata = [],
        ?string $fromStatus = null,
        ?string $toStatus = null
    ): EvaluationAuditEvent {
        return $this->auditEvents()->create([
            'actor_id' => $actor?->id,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => empty($metadata) ? null : $metadata,
            'occurred_at' => now(),
        ]);
    }

    public function scopeVisibleToAgent($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PUBLISHED_TO_AGENT,
            self::STATUS_AGENT_ACCEPTED,
            self::STATUS_AGENT_DISPUTED,
            self::STATUS_DISPUTE_RESOLVED,
            self::STATUS_CLOSED,
            self::STATUS_AGENT_REVIEWED,
            self::STATUS_COMMITMENT_REGISTERED,
        ]);
    }

    public function scopeAi($query)
    {
        return $query->where('type', 'ai');
    }

    public function scopeManual($query)
    {
        return $query->where('type', 'manual');
    }

    public function scopeSearchIndex($query, string $term)
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $needle = mb_strtolower($term);
        $like = "%{$needle}%";
        $date = self::dateFromSearchTerm($term);
        $statusMatches = collect(self::statusLabels())
            ->filter(fn (string $label, string $status) => str_contains(mb_strtolower($label), $needle) || str_contains(mb_strtolower($status), $needle))
            ->keys()
            ->all();

        return $query->where(function ($query) use ($term, $like, $date, $statusMatches) {
            $query
                ->whereRaw('LOWER(COALESCE(evaluations.type, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(evaluations.status, \'\')) LIKE ?', [$like]);

            if (ctype_digit($term)) {
                $query->orWhere('evaluations.id', (int) $term);
            }

            if ($statusMatches !== []) {
                $query->orWhereIn('evaluations.status', $statusMatches);
            }

            if ($date !== null) {
                $query
                    ->orWhereDate('evaluations.created_at', $date)
                    ->orWhereHas('interaction', function ($interactionQuery) use ($date) {
                        $interactionQuery->where(function ($query) use ($date) {
                            $query->whereDate('occurred_at', $date)
                                ->orWhereDate('uploaded_at', $date)
                                ->orWhereDate('created_at', $date);
                        });
                    });
            }

            $query
                ->orWhereHas('agent', fn ($userQuery) => self::whereUserMatchesSearch($userQuery, $like))
                ->orWhereHas('evaluator', fn ($userQuery) => self::whereUserMatchesSearch($userQuery, $like))
                ->orWhereHas('reviewer', fn ($userQuery) => self::whereUserMatchesSearch($userQuery, $like))
                ->orWhereHas('publisher', fn ($userQuery) => self::whereUserMatchesSearch($userQuery, $like))
                ->orWhereHas('campaign', function ($campaignQuery) use ($like) {
                    $campaignQuery->where(function ($query) use ($like) {
                        $query->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$like])
                            ->orWhereHas('parent', fn ($parentQuery) => $parentQuery->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$like]));
                    });
                })
                ->orWhereHas('interaction', function ($interactionQuery) use ($like) {
                    $interactionQuery->where(function ($query) use ($like) {
                        foreach ([
                            'call_sn',
                            'external_id',
                            'file_name',
                            'source_type',
                            'channel',
                            'direction',
                            'contact_reason',
                            'outcome',
                            'customer_reference',
                            'queue_name',
                            'product_name',
                            'priority',
                        ] as $index => $column) {
                            $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                            $query->{$method}("LOWER(COALESCE({$column}, '')) LIKE ?", [$like]);
                        }
                    });
                });
        });
    }

    private static function whereUserMatchesSearch($query, string $like): void
    {
        $query->where(function ($query) use ($like) {
            $query
                ->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(paternal_surname, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(maternal_surname, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(username, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                ->orWhereRaw("LOWER(COALESCE(name, '') || ' ' || COALESCE(paternal_surname, '') || ' ' || COALESCE(maternal_surname, '')) LIKE ?", [$like]);
        });
    }

    private static function dateFromSearchTerm(string $term): ?string
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $term);

                if ($date !== false && $date->format($format) === $term) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable) {
                //
            }
        }

        return null;
    }

    public function scopeAvailableForReviewBy($query, User $user)
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where(function ($query) use ($user) {
            $query
                ->whereNull('review_claimed_by')
                ->orWhere('review_claimed_by', $user->id)
                ->orWhereNull('review_claim_expires_at')
                ->orWhere('review_claim_expires_at', '<=', now());
        });
    }

    public function scopeFinalForReporting($query)
    {
        return $query->where(function ($query) {
            $query
                ->where('evaluations.type', 'manual')
                ->orWhereDoesntHave('interaction.manualEvaluation');
        });
    }

    public function scopeForUser($query, $user)
    {
        // 1. View All
        if ($user->hasAnyRole(['admin', 'qa_manager'])) {
            return $query;
        }

        // 2. Manager: View Managed Campaigns
        if ($user->hasRole('manager')) {
            return $query->whereIn('campaign_id', Campaign::visibleIdsForUser($user));
        }

        // 3. QA Manager / Coordinator: View Monitors' Evaluations & Assigned Campaigns
        // "qa manager vea el de sus monitores y campañas"
        if ($user->hasRole('qa_manager') || $user->hasRole('qa_coordinator')) {
            return $query->where(function ($q) use ($user) {
                // Defines "Sus Monitores"
                $monitorIds = $user->monitors->pluck('id');
                if ($monitorIds->isNotEmpty()) {
                    $q->whereIn('evaluator_id', $monitorIds);
                }

                // Defines "Sus Campañas" (direct assignment if any, or implied by team)
                // Assuming QA Managers might also have campaign_managers entries or similar.
                // For now, if they are assigned to a campaign via pivot (unlikely for QA but possible) check there.
                // Or if they are supervisors in a campaign.
                $q->orWhereIn('campaign_id', Campaign::visibleIdsForUser($user));
            });
        }

        // 4. Supervisor: View Agents' Evaluations & Campaigns
        // "supervisor el de sus agente y campañas"
        if ($user->hasRole('supervisor')) {
            return $query->where(function ($q) use ($user) {
                $teamAgents = \App\Models\CampaignUserAssignment::where('supervisor_id', $user->id)
                    ->where('is_active', true)
                    ->pluck('agent_id');

                $q->whereHas('interaction', function ($interactionQuery) use ($user) {
                    $interactionQuery->where('supervisor_id', $user->id);
                });

                if ($teamAgents->isNotEmpty()) {
                    $q->orWhereIn('agent_id', $teamAgents);
                }
            });
        }

        // 5. Monitor: View Own Evaluations Only
        if ($user->hasRole('qa_monitor')) {
            return $query->where(function ($q) use ($user) {
                $q->where('evaluator_id', $user->id)
                    ->orWhereIn('campaign_id', Campaign::visibleIdsForUser($user));
            });
        }

        // 6. Agent: View Own Evaluations Only
        if ($user->hasRole('agent')) {
            return $query->where('agent_id', $user->id)->visibleToAgent();
        }

        // Default: No access
        return $query->whereRaw('1 = 0');
    }

    public function isVisibleToAgent(): bool
    {
        return in_array($this->status, [
            self::STATUS_PUBLISHED_TO_AGENT,
            self::STATUS_AGENT_ACCEPTED,
            self::STATUS_AGENT_DISPUTED,
            self::STATUS_DISPUTE_RESOLVED,
            self::STATUS_CLOSED,
            self::STATUS_AGENT_REVIEWED,
            self::STATUS_COMMITMENT_REGISTERED,
        ], true);
    }

    public function isPendingMonitorReview(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_MONITOR_REVIEW,
            self::STATUS_AI_REANALYSIS_REQUESTED,
            'ai_done',
        ], true);
    }

    public function hasActiveReviewClaim(): bool
    {
        return filled($this->review_claimed_by)
            && $this->review_claim_expires_at
            && $this->review_claim_expires_at->isFuture();
    }

    public function isReviewClaimedBy(User $user): bool
    {
        return $this->hasActiveReviewClaim()
            && (int) $this->review_claimed_by === (int) $user->id;
    }

    public function isReviewClaimedByOther(User $user): bool
    {
        return $this->hasActiveReviewClaim()
            && (int) $this->review_claimed_by !== (int) $user->id;
    }

    public function claimForReview(User $user, $expiresAt): void
    {
        $this->forceFill([
            'review_claimed_by' => $user->id,
            'review_claimed_at' => now(),
            'review_claim_expires_at' => $expiresAt,
        ])->save();
    }

    public function releaseReviewClaim(): void
    {
        $this->forceFill([
            'review_claimed_by' => null,
            'review_claimed_at' => null,
            'review_claim_expires_at' => null,
        ])->save();
    }

    public function canBePublished(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_MONITOR_REVIEW,
            self::STATUS_APPROVED,
            'ai_done',
        ], true);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function canBeClosed(): bool
    {
        return ! $this->isClosed() && in_array($this->status, [
            self::STATUS_AI_FAILED,
            self::STATUS_PUBLISHED_TO_AGENT,
            self::STATUS_AGENT_ACCEPTED,
            self::STATUS_DISPUTE_RESOLVED,
        ], true);
    }

    public function canBeReopened(): bool
    {
        return $this->isClosed() && filled($this->previous_status_before_close);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING_AI => 'Pendiente IA',
            self::STATUS_AI_PROCESSING => 'Procesando IA',
            self::STATUS_AI_FAILED => 'IA fallida',
            self::STATUS_PENDING_MONITOR_REVIEW => 'Pendiente revision monitor',
            self::STATUS_AI_REANALYSIS_REQUESTED => 'Reanalisis solicitado',
            self::STATUS_APPROVED => 'Aprobada',
            self::STATUS_PUBLISHED_TO_AGENT => 'Publicada al asesor',
            self::STATUS_AGENT_ACCEPTED => 'Aceptada por asesor',
            self::STATUS_AGENT_DISPUTED => 'Disputada por asesor',
            self::STATUS_DISPUTE_RESOLVED => 'Disputa resuelta',
            self::STATUS_CLOSED => 'Cerrada',
            self::STATUS_AGENT_REVIEWED => 'Revisada por el asesor',
            self::STATUS_COMMITMENT_REGISTERED => 'Compromiso registrado',
        ];
    }

    public static function statusLabel(string $status): string
    {
        return self::statusLabels()[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
