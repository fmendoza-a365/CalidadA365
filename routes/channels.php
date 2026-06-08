<?php

use Illuminate\Support\Facades\Broadcast;

if (config('broadcasting.default') !== 'null') {
    Broadcast::channel('interactions', function ($user) {
        return $user->can('view_transcripts') || $user->hasAnyRole(['admin', 'qa_manager', 'qa_coordinator', 'qa_monitor', 'supervisor', 'manager']);
    });
}
