<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DisputeResolution;

class DisputeResolutionPolicy
{
    public function resolve(User $user, DisputeResolution $dispute): bool
    {
        return $user->hasAnyRole(['admin', 'qa_manager']) && !$dispute->resolved_at;
    }
}
