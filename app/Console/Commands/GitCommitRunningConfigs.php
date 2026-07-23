<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use App\Models\Device\Device;
use App\Jobs\GitCreateRunFileJob;
use App\Jobs\GitCommitRunningConfigsJob;

class GitCommitRunningConfigs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:gitCommitRunningConfigs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Write device running-configs to disk and commit/push to the running-configs git repo.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $repoPath = base_path(env('RUNNING_CONFIGS_REPO_PATH', 'storage/git-repos/running-configs'));

        if (!is_dir($repoPath)) {
            $this->error("Running configs repo path does not exist: {$repoPath}");
            return 1;
        }

        $devices = Device::all();

        $jobs = [];
        foreach ($devices as $device) {
            $jobs[] = new GitCreateRunFileJob($device->id, $repoPath);
        }

        if (empty($jobs)) {
            $this->warn("No devices found — nothing to dispatch.");
            return 0;
        }

        Bus::batch($jobs)
            ->then(function (\Illuminate\Bus\Batch $batch) use ($repoPath) {
                GitCommitRunningConfigsJob::dispatch($repoPath);
            })
            ->name('GitCommitRunningConfigs')
            ->dispatch();

        $this->info("Dispatched " . count($jobs) . " GitCreateRunFileJob(s) in a batch. GitCommitRunningConfigsJob will run automatically when all jobs complete.");

        return 0;
    }
}
