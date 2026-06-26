<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device\Device;

class GitCommitRunningConfigs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:gitCommitRunningConfigs {--push : Push commits to the remote git repository after committing}';

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
        $written = 0;
        $skipped = 0;

        foreach ($devices as $device) {
            $name = $device->getName();

            if (!$name) {
                $this->line("  [SKIP] Device ID {$device->id} — getName() returned null, skipping.");
                $skipped++;
                continue;
            }

            $output = $device->getLatestOutputs('run');

            if (!$output || !$output->data) {
                $this->line("  [SKIP] Device '{$name}' (ID {$device->id}) — no 'run' output found, skipping.");
                $skipped++;
                continue;
            }

            $filename = $repoPath . '/' . $name . '.txt';
            file_put_contents($filename, $output->data);
            $this->line("  [OK]   Wrote config for '{$name}'.");
            $written++;
        }

        $this->info("Written: {$written} | Skipped: {$skipped}");

        if ($written === 0) {
            $this->warn("No configs were written — nothing to commit.");
            return 0;
        }

        if ($this->option('push')) {
            $timestamp = now()->format('Y-m-d H:i:s');
            $commitMessage = "Auto-commit running configs - {$timestamp}";

            $this->info("Running git add...");
            $addOutput = shell_exec("cd " . escapeshellarg($repoPath) . " && git add . 2>&1");
            $this->line($addOutput);

            $this->info("Running git commit...");
            $commitOutput = shell_exec("cd " . escapeshellarg($repoPath) . " && git commit -m " . escapeshellarg($commitMessage) . " 2>&1");
            $this->line($commitOutput);

            $this->info("Running git push...");
            $pushOutput = shell_exec("cd " . escapeshellarg($repoPath) . " && git push 2>&1");
            $this->line($pushOutput);

            $this->info("Done! Running configs committed and pushed successfully.");
        } else {
            $this->info("Done! Running configs written to disk. Use --push to git add, commit, and push to the remote repository.");
        }
        return 0;
    }
}
