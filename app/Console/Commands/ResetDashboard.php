<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DashboardWidget;

class ResetDashboard extends Command
{
    protected $signature = 'dashboard:reset {--user= : User ID to reset dashboard for}';
    protected $description = 'Reset dashboard widgets for a user';

    public function handle()
    {
        $userId = $this->option('user') ?? auth()->id();
        
        if (!$userId) {
            $this->error('No user specified. Use --user=ID');
            return 1;
        }

        $count = DashboardWidget::where('user_id', $userId)->count();
        
        if ($count === 0) {
            $this->info('No widgets found for this user.');
            return 0;
        }

        if ($this->confirm("Delete {$count} widgets for user {$userId}?")) {
            DashboardWidget::where('user_id', $userId)->delete();
            $this->info("Deleted {$count} widgets successfully.");
            $this->info('You can now create fresh widgets in the dashboard.');
        }

        return 0;
    }
}
