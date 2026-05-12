<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DisputeResolution;

class DisputeResolutionPolicy
{
    public function supervisorReview(User $user, DisputeResolution $dispute): bool
    {
        return !$dispute->isResolved()
            && !$dispute->qa_reviewed_at
            && $user->hasRole('supervisor')
            && $dispute->evaluation?->interaction?->supervisor_id === $user->id;
    }

    public function qaReview(User $user, DisputeResolution $dispute): bool
    {
        if ($dispute->isResolved() || $dispute->qa_reviewed_at || !$user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor'])) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'qa_manager'])) {
            return true;
        }

        return \App\Models\Evaluation::forUser($user)->whereKey($dispute->evaluation_id)->exists();
    }

    public function coordinatorReview(User $user, DisputeResolution $dispute): bool
    {
        if ($dispute->isResolved() || !$dispute->qa_reviewed_at || $dispute->coordinator_reviewed_at || !$user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator'])) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'qa_manager'])) {
            return true;
        }

        return \App\Models\Evaluation::forUser($user)->whereKey($dispute->evaluation_id)->exists();
    }

    public function resolve(User $user, DisputeResolution $dispute): bool
    {
        return $user->hasAnyRole(['admin', 'qa_manager']) && !$dispute->isResolved();
    }
}
