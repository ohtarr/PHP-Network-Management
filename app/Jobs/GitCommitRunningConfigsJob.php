<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GitCommitRunningConfigsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 1;

    protected string $repoPath;

    /**
     * Create a new job instance.
     */
    public function __construct(string $repoPath)
    {
        $this->repoPath = $repoPath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Clean up any stale git lock file left over from a previous interrupted run
        $lockFile = $this->repoPath . '/.git/index.lock';
        if (file_exists($lockFile)) {
            Log::warning("GitCommitRunningConfigsJob: Stale index.lock found, removing...");
            unlink($lockFile);
        }

        $timestamp = now()->format('Y-m-d H:i:s');
        $commitMessage = "Auto-commit running configs - {$timestamp}";
        $path = escapeshellarg($this->repoPath);

        Log::info("GitCommitRunningConfigsJob: Running git add...");
        $addOutput = shell_exec("cd {$path} && git add . 2>&1");
        Log::info("GitCommitRunningConfigsJob: git add output: {$addOutput}");

        Log::info("GitCommitRunningConfigsJob: Running git commit...");
        $commitOutput = shell_exec("cd {$path} && git commit -m " . escapeshellarg($commitMessage) . " 2>&1");
        Log::info("GitCommitRunningConfigsJob: git commit output: {$commitOutput}");

        // Build authenticated push URL from env vars so credentials are never stored in .git/config
        $baseUrl  = env('RUNNING_CONFIGS_REMOTE_URL');
        $user     = env('GITLAB_USERNAME');
        $token    = env('GITLAB_TOKEN');
        $authUrl  = preg_replace('#https://#', "https://{$user}:{$token}@", $baseUrl);

        Log::info("GitCommitRunningConfigsJob: Running git push...");
        $pushOutput = shell_exec("cd {$path} && git push " . escapeshellarg($authUrl) . " 2>&1");
        Log::info("GitCommitRunningConfigsJob: git push output: {$pushOutput}");

        Log::info("GitCommitRunningConfigsJob: Done. Running configs committed and pushed.");
    }
}
