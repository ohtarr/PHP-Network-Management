<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Device\Device;

class GitCreateRunFileJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 1;

    protected int $deviceId;
    protected string $repoPath;

    /**
     * Create a new job instance.
     */
    public function __construct(int $deviceId, string $repoPath)
    {
        $this->deviceId = $deviceId;
        $this->repoPath = $repoPath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        $device = Device::findOrFail($this->deviceId);

        $nbdevice = $device->getNetboxDevice();
        if (!$nbdevice) {
            Log::info("GitCreateRunFileJob: Device ID {$this->deviceId} — No Netbox device found, skipping.");
            return;
        }

        $name = $nbdevice->generateDnsName();
        if (!$name) {
            Log::info("GitCreateRunFileJob: Device ID {$this->deviceId} — No DNS name, skipping.");
            return;
        }

        $output = $device->getLatestOutputs('run');

        if (!$output || !$output->data) {
            Log::info("GitCreateRunFileJob: Device '{$name}' (ID {$this->deviceId}) — no 'run' output found, skipping.");
            return;
        }

        $filename = $this->repoPath . '/' . $name . '.txt';

        // Load filter patterns from config/gitconfig.php
        $filters = config('gitconfig.line_filters', []);

        // Split into lines, remove blank lines and any line matching a filter pattern, then rejoin
        $lines = explode("\n", $output->data);
        $lines = array_filter($lines, function (string $line) use ($filters) {
            // Remove blank / whitespace-only lines
            if (trim($line) === '') {
                return false;
            }
            // Remove lines matching any filter pattern
            foreach ($filters as $pattern) {
                if (preg_match($pattern, $line)) {
                    return false;
                }
            }
            return true;
        });
        $filtered = implode("\n", $lines);

        file_put_contents($filename, $filtered);
        Log::info("GitCreateRunFileJob: Wrote config for '{$name}' (ID {$this->deviceId}).");
    }
}
