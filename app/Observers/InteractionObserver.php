<?php

namespace App\Observers;

use App\Events\InteractionStatusChanged;
use App\Models\Interaction;

class InteractionObserver
{
    public function updated(Interaction $interaction): void
    {
        if ($interaction->isDirty('status') || $interaction->isDirty('transcription_status')) {
            broadcast(new InteractionStatusChanged($interaction));
        }
    }
}
