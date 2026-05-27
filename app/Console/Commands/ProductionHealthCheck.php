<?php

namespace App\Console\Commands;

use App\Models\Evaluation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionHealthCheck extends Command
{
    protected $signature = 'qa:health {--json : Output machine-readable JSON}';

    protected $description = 'Report production health signals for queues, failed jobs, and AI evaluation states';

    public function handle(): int
    {
        $data = [
            'jobs_pending' => $this->tableCount('jobs'),
            'failed_jobs' => $this->tableCount('failed_jobs'),
            'oldest_pending_job_minutes' => $this->oldestPendingJobMinutes(),
            'ai_pending' => Evaluation::where('status', Evaluation::STATUS_PENDING_AI)->count(),
            'ai_processing' => Evaluation::where('status', Evaluation::STATUS_AI_PROCESSING)->count(),
            'ai_failed' => Evaluation::where('status', Evaluation::STATUS_AI_FAILED)->count(),
            'monitor_pending' => Evaluation::where('status', Evaluation::STATUS_PENDING_MONITOR_REVIEW)->count(),
            'disputed' => Evaluation::where('status', Evaluation::STATUS_AGENT_DISPUTED)->count(),
            'closed' => Evaluation::where('status', Evaluation::STATUS_CLOSED)->count(),
        ];

        $isHealthy = $data['failed_jobs'] === 0
            && $data['ai_failed'] <= (int) env('QA_HEALTH_MAX_AI_FAILED', 0)
            && $data['jobs_pending'] <= (int) env('QA_HEALTH_MAX_PENDING_JOBS', 100)
            && $data['oldest_pending_job_minutes'] <= (int) env('QA_HEALTH_MAX_OLDEST_JOB_MINUTES', 60);
        $data['status'] = $isHealthy ? 'ok' : 'attention';

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT));

            return $isHealthy ? Command::SUCCESS : Command::FAILURE;
        }

        $this->info('QA365 production health');
        $this->table(
            ['Signal', 'Value'],
            collect($data)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if (! $isHealthy) {
            $this->warn('There are failed jobs. Review queue failures before continuing normal operation.');
        }

        return $isHealthy ? Command::SUCCESS : Command::FAILURE;
    }

    private function tableCount(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    private function oldestPendingJobMinutes(): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        $oldest = DB::table('jobs')->min('created_at');

        if (! $oldest) {
            return 0;
        }

        return max(0, (int) floor((time() - (int) $oldest) / 60));
    }
}
