<?php

namespace App\Events;

use App\Models\Interaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InteractionStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Interaction $interaction,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('interactions'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'interaction.status-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->interaction->id,
            'status' => $this->interaction->status,
            'has_evaluation' => $this->interaction->evaluation()->exists(),
            'is_transcribing' => $this->interaction->isTranscribing(),
            'is_failed' => $this->interaction->isTranscriptionFailed(),
        ];
    }
}
