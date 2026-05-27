<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\Models\User;

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
        return $this->canReview($user, $evaluation)
            && ! $evaluation->isClosed()
            && $evaluation->canBePublished();
    }

    public function reanalyze(User $user, Evaluation $evaluation): bool
    {
        return $this->canReview($user, $evaluation)
            && $evaluation->type === 'ai'
            && ! $evaluation->isClosed()
            && ! $evaluation->isVisibleToAgent();
    }

    public function close(User $user, Evaluation $evaluation): bool
    {
        return ($user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator']) || $user->can('manage_evaluation_lifecycle'))
            && $this->canReview($user, $evaluation)
            && $evaluation->canBeClosed();
    }

    public function reopen(User $user, Evaluation $evaluation): bool
    {
        if (! $user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator']) && ! $user->can('manage_evaluation_lifecycle')) {
            return false;
        }

        return $evaluation->canBeReopened()
            && ($user->hasRole('admin') || Evaluation::forUser($user)->whereKey($evaluation->id)->exists());
    }

    private function canReview(User $user, Evaluation $evaluation): bool
    {
        if (! $user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor'])) {
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
