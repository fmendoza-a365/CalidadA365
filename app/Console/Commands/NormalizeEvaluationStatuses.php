<?php

namespace App\Console\Commands;

use App\Models\Evaluation;
use Illuminate\Console\Command;

class NormalizeEvaluationStatuses extends Command
{
    protected $signature = 'qa:normalize-evaluation-statuses {--apply : Persist changes instead of dry-running}';

    protected $description = 'Normalize legacy evaluation statuses into the current lifecycle states';

    private array $statusMap = [
        'visible_to_agent' => Evaluation::STATUS_PUBLISHED_TO_AGENT,
        'agent_responded' => Evaluation::STATUS_AGENT_ACCEPTED,
        'disputed' => Evaluation::STATUS_AGENT_DISPUTED,
        'resolved' => Evaluation::STATUS_DISPUTE_RESOLVED,
        'final' => Evaluation::STATUS_CLOSED,
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];

        foreach ($this->statusMap as $legacyStatus => $newStatus) {
            $count = Evaluation::where('status', $legacyStatus)->count();
            $rows[] = [$legacyStatus, $newStatus, $count];

            if (! $apply || $count === 0) {
                continue;
            }

            Evaluation::where('status', $legacyStatus)
                ->each(function (Evaluation $evaluation) use ($legacyStatus, $newStatus) {
                    $evaluation->update(['status' => $newStatus]);
                    $evaluation->recordAuditEvent('legacy_status_normalized', null, [
                        'legacy_status' => $legacyStatus,
                    ], $legacyStatus, $newStatus);
                });
        }

        $this->table(['Legacy status', 'Current status', 'Rows'], $rows);
        $this->info($apply ? 'Legacy statuses normalized.' : 'Dry run complete. Re-run with --apply to persist.');

        return Command::SUCCESS;
    }
}
