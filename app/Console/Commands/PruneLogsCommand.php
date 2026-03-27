<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Log\Log;
use Illuminate\Support\Carbon;

class PruneLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:prune
                            {--days= : Number of days to retain logs (overrides LOG_RETENTION_DAYS env variable)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete log entries older than the configured retention period (LOG_RETENTION_DAYS).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // --days option overrides the env variable; env falls back to 90
        // Use $_ENV directly so putenv() changes in tests are picked up immediately.
        $days = (int) ($this->option('days') ?? ($_ENV['LOG_RETENTION_DAYS'] ?? env('LOG_RETENTION_DAYS', 90)));

        if ($days <= 0) {
            $this->error("Retention period must be greater than 0 days. Got: {$days}");
            return self::FAILURE;
        }

        $cutoff  = Carbon::now()->subDays($days);
        $deleted = Log::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} log " . ($deleted === 1 ? 'entry' : 'entries') . " older than {$days} days.");

        return self::SUCCESS;
    }
}
