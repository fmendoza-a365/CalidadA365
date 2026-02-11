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
        return $user->hasAnyRole(['admin', 'qa_manager'])
            || $user->id === $evaluation->agent_id
            || $user->id === $evaluation->interaction->supervisor_id;
    }

    public function respond(User $user, Evaluation $evaluation): bool
    {
        return $user->id === $evaluation->agent_id
            && $evaluation->status === 'visible_to_agent';
    }
}
