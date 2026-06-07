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

        // Determine target status
        $targetStatus = Evaluation::STATUS_AGENT_ACCEPTED;
        if ($data['response_type'] === 'reviewed') {
            $targetStatus = Evaluation::STATUS_AGENT_REVIEWED;
        } elseif ($data['response_type'] === 'commitment') {
            $targetStatus = Evaluation::STATUS_COMMITMENT_REGISTERED;
        }

        $evaluation->update(['status' => $targetStatus]);

        $evaluation->recordAuditEvent($data['response_type'] === 'accept' ? 'agent_accepted' : $data['response_type'], $agent, [
            'agent_response_id' => $response->id,
            'commitment_present' => filled($data['commitment_comment'] ?? null),
        ], $fromStatus, $targetStatus);

        // Notify hierarchy for reviewed/commitment/accept
        $notificationType = $data['response_type'] === 'commitment' ? 'commitment' : 'reviewed';
        $notification = new \App\Notifications\EvaluationReviewed($evaluation, $agent, $notificationType);

        // 1. Notify the Monitor (Evaluator) who performed the evaluation
        if ($evaluation->evaluator) {
            $evaluation->evaluator->notify($notification);
        }

        // 2. Notify the Supervisor associated (via CampaignUserAssignment or Interaction)
        $supervisor = $evaluation->interaction?->supervisor;
        if (!$supervisor) {
            $supervisor = \App\Models\CampaignUserAssignment::where('agent_id', $agent->id)
                ->where('campaign_id', $evaluation->campaign_id)
                ->where('is_active', true)
                ->first()?->supervisor;
        }
        if ($supervisor) {
            $supervisor->notify($notification);
        }

        // 3. Notify QA Coordinators / Campaign Managers associated with this campaign
        $campaign = $evaluation->campaign;
        if ($campaign) {
            $managers = $campaign->managers()->get();
            foreach ($managers as $manager) {
                if ($manager->hasAnyRole(['qa_coordinator', 'qa_manager'])) {
                    $manager->notify($notification);
                }
            }
        }

        return $response;
    }
}
