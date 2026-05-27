<?php

namespace Tests\Feature;

use App\Models\DisputeResolution;
use App\Models\Evaluation;
use App\Models\Interaction;
use Database\Seeders\CurrentFunctionalityDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CurrentFunctionalityDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_functionality_demo_seeder_creates_representative_examples(): void
    {
        $this->seed(CurrentFunctionalityDemoSeeder::class);

        $this->assertDatabaseHas('campaigns', [
            'name' => 'Demo QA365 - Funcionalidad Actual',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'demo.monitor@qa365.local',
        ]);

        foreach ([
            Evaluation::STATUS_PENDING_AI,
            Evaluation::STATUS_AI_PROCESSING,
            Evaluation::STATUS_AI_FAILED,
            Evaluation::STATUS_PENDING_MONITOR_REVIEW,
            Evaluation::STATUS_AGENT_DISPUTED,
            Evaluation::STATUS_DISPUTE_RESOLVED,
            Evaluation::STATUS_CLOSED,
        ] as $status) {
            $this->assertTrue(
                Evaluation::where('status', $status)->exists(),
                "Expected demo evaluation with status [{$status}]."
            );
        }

        $this->assertDatabaseHas('dispute_resolutions', [
            'status' => DisputeResolution::STATUS_PENDING_SUPERVISOR_REVIEW,
        ]);
        $this->assertDatabaseHas('dispute_resolutions', [
            'status' => DisputeResolution::STATUS_RESOLVED,
        ]);

        $this->assertTrue(
            Evaluation::where('type', 'manual')
                ->whereHas('interaction.evaluations', fn ($query) => $query->where('type', 'ai'))
                ->exists()
        );

        $audioInteraction = Interaction::where('source_type', 'audio')
            ->where('metadata->demo', true)
            ->first();

        $this->assertNotNull($audioInteraction);
        $this->assertSame('completed', $audioInteraction->transcription_status);
        $this->assertStringContainsString('[00:00] Agente:', $audioInteraction->transcript_text);
        $this->assertNotEmpty($audioInteraction->metadata['sentiment_segments'] ?? []);
        $this->assertTrue(Storage::disk(config('filesystems.default', 'local'))->exists($audioInteraction->file_path));
    }
}
