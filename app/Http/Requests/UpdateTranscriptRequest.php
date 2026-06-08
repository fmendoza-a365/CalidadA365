<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return \App\Models\Interaction::forUser($this->user())
            ->where('id', $this->route('interaction')->id)
            ->exists();
    }

    public function rules(): array
    {
        $channelOptions = implode(',', array_keys(\App\Http\Controllers\TranscriptController::CHANNEL_OPTIONS));
        $directionOptions = implode(',', array_keys(\App\Http\Controllers\TranscriptController::DIRECTION_OPTIONS));
        $languageOptions = implode(',', array_keys(\App\Http\Controllers\TranscriptController::LANGUAGE_OPTIONS));
        $outcomeOptions = implode(',', array_keys(\App\Http\Controllers\TranscriptController::OUTCOME_OPTIONS));
        $priorityOptions = implode(',', array_keys(\App\Http\Controllers\TranscriptController::PRIORITY_OPTIONS));
        $diarizationOptions = implode(',', array_keys(\App\Http\Controllers\TranscriptController::DIARIZATION_OPTIONS));

        return [
            'campaign_id' => 'required|exists:campaigns,id',
            'agent_id' => 'required|exists:users,id',
            'occurred_at' => 'required|date',
            'call_sn' => 'nullable|string|max:100',
            'external_id' => 'nullable|string|max:120',
            'channel' => "nullable|in:{$channelOptions}",
            'direction' => "nullable|in:{$directionOptions}",
            'language' => "nullable|in:{$languageOptions}",
            'contact_reason' => 'nullable|string|max:160',
            'outcome' => "nullable|in:{$outcomeOptions}",
            'customer_reference' => 'nullable|string|max:120',
            'queue_name' => 'nullable|string|max:120',
            'product_name' => 'nullable|string|max:120',
            'priority' => "nullable|in:{$priorityOptions}",
            'tags' => 'nullable|string|max:500',
            'diarization_mode' => "nullable|in:{$diarizationOptions}",
            'analyze_emotion' => 'nullable|boolean',
            'detect_critical_compliance' => 'nullable|boolean',
            'ai_context' => 'nullable|string|max:1000',
            'transcript_text' => 'nullable|string',
        ];
    }
}
