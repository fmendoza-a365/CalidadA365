<?php

namespace App\Services;

use App\Models\AgentResponse;
use App\Models\DisputeResolution;
use App\Models\Evaluation;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AgentFeedbackResponseService
{
    public function store(Evaluation $evaluation, User $agent, array $data): AgentResponse
    {
        $response = AgentResponse::create([
            'evaluation_id' => $evaluation->id,
            'agent_id' => $agent->id,
            'response_type' => $data['response_type'],
            'commitment_comment' => $data['commitment_comment'] ?? null,
            'dispute_reason' => $data['dispute_reason'] ?? null,
            'disputed_items' => $data['disputed_items'] ?? null,
        ]);

        $fromStatus = $evaluation->status;

        if ($data['response_type'] === 'dispute') {
            $dispute = DisputeResolution::create([
                'agent_response_id' => $response->id,
                'evaluation_id' => $evaluation->id,
                'status' => DisputeResolution::STATUS_PENDING_SUPERVISOR_REVIEW,
            ]);

            $evaluation->update(['status' => Evaluation::STATUS_AGENT_DISPUTED]);

            $evaluation->recordAuditEvent('agent_disputed', $agent, [
                'agent_response_id' => $response->id,
                'dispute_id' => $dispute->id,
                'disputed_items_count' => count($data['disputed_items'] ?? []),
            ], $fromStatus, Evaluation::STATUS_AGENT_DISPUTED);

            if ($evaluation->interaction?->supervisor) {
                $evaluation->interaction->supervisor->notify(new \App\Notifications\DisputeOpened($evaluation, $agent));
            }

            $qaManagers = Role::where('name', 'qa_manager')->where('guard_name', 'web')->exists()
                ? User::role('qa_manager')->get()
                : collect();

            foreach ($qaManagers as $manager) {
                $manager->notify(new \App\Notifications\DisputeOpened($evaluation, $agent));
            }

            return $response;
        }

        $evaluation->update(['status' => Evaluation::STATUS_AGENT_ACCEPTED]);

        $evaluation->recordAuditEvent('agent_accepted', $agent, [
            'agent_response_id' => $response->id,
            'commitment_present' => filled($data['commitment_comment'] ?? null),
        ], $fromStatus, Evaluation::STATUS_AGENT_ACCEPTED);

        return $response;
    }
}
