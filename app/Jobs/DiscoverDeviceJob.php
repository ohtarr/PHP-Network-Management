<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Device\Device;
use Illuminate\Support\Facades\Log;

class DiscoverDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 1;
    public $options;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($options)
    {
        $this->options = $options;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(isset($this->options['id']))
        {
            $device = Device::findOrFail($this->options['id']);
        } else {
            Log::error("DiscoverDeviceJob failed: No device ID provided in options.", ['options' => $this->options]);
            return;
        }
        Log::info("DiscoverDeviceJob starting for device ID {$device->id}.");
        $result = $device->discover();
        if($result)
        {
            Log::info("DiscoverDeviceJob completed successfully for device ID {$device->id}.", ['type' => get_class($result)]);
        } else {
            Log::warning("DiscoverDeviceJob completed but discovery returned no result for device ID {$device->id}. Device may be unreachable, have no valid credentials, or be an unrecognized type.");
        }
    }
}
