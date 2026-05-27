<?php

namespace App\Console\Commands;

use App\Models\Evaluation;
use App\Models\Interaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrunePrivateMedia extends Command
{
    protected $signature = 'qa:prune-private-media
        {--days=365 : Retain files and transcripts for this many days}
        {--apply : Persist deletions/redactions}
        {--redact-transcripts : Redact transcript text for eligible interactions}';

    protected $description = 'Dry-run or apply retention cleanup for private audio/transcript files';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $apply = (bool) $this->option('apply');
        $redactTranscripts = (bool) $this->option('redact-transcripts');
        $cutoff = now()->subDays($days);
        $disk = config('filesystems.default', 'local');

        $query = Interaction::query()
            ->where('occurred_at', '<=', $cutoff)
            ->whereDoesntHave('evaluations', function ($query) {
                $query->where('status', '!=', Evaluation::STATUS_CLOSED);
            });

        $eligible = $query->count();
        $filesDeleted = 0;
        $transcriptsRedacted = 0;

        $query->chunkById(200, function ($interactions) use ($apply, $redactTranscripts, $disk, &$filesDeleted, &$transcriptsRedacted) {
            foreach ($interactions as $interaction) {
                if ($interaction->file_path && Storage::disk($disk)->exists($interaction->file_path)) {
                    $filesDeleted++;
                    if ($apply) {
                        Storage::disk($disk)->delete($interaction->file_path);
                    }
                }

                if ($redactTranscripts && filled($interaction->transcript_text)) {
                    $transcriptsRedacted++;
                    if ($apply) {
                        $interaction->update([
                            'transcript_text' => '[REDACTED BY RETENTION POLICY]',
                            'metadata' => array_merge($interaction->metadata ?? [], [
                                'retention_redacted_at' => now()->toISOString(),
                            ]),
                        ]);
                    }
                }
            }
        });

        $this->table(['Signal', 'Value'], [
            ['mode', $apply ? 'apply' : 'dry-run'],
            ['retention_days', $days],
            ['eligible_interactions', $eligible],
            ['files_to_delete', $filesDeleted],
            ['transcripts_to_redact', $transcriptsRedacted],
        ]);

        if (! $apply) {
            $this->warn('Dry-run only. Re-run with --apply to persist changes.');
        }

        return Command::SUCCESS;
    }
}
