<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Evaluation;

class EvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->id === $evaluation->agent_id) {
            return $evaluation->isVisibleToAgent();
        }

        return Evaluation::forUser($user)->whereKey($evaluation->id)->exists()
            || $user->id === $evaluation->interaction->supervisor_id;
    }

    public function respond(User $user, Evaluation $evaluation): bool
    {
        return $user->id === $evaluation->agent_id
            && $evaluation->status === Evaluation::STATUS_PUBLISHED_TO_AGENT;
    }

    public function publish(User $user, Evaluation $evaluation): bool
    {
        return $this->canReview($user, $evaluation) && $evaluation->canBePublished();
    }

    public function reanalyze(User $user, Evaluation $evaluation): bool
    {
        return $this->canReview($user, $evaluation)
            && $evaluation->type === 'ai'
            && !$evaluation->isVisibleToAgent();
    }

    private function canReview(User $user, Evaluation $evaluation): bool
    {
        if (!$user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor'])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return Evaluation::forUser($user)->whereKey($evaluation->id)->exists()
            || $evaluation->interaction->uploaded_by === $user->id
            || $evaluation->evaluator_id === $user->id;
    }
}
