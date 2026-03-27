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
use App\Models\Log\Log as DbLog;

class ScanDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $device;
    public $timeout = 300;
    public $tries = 1;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->device = Device::findOrFail($id);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DbLog::log("ScanDeviceJob starting for device ID {$this->device->id}.", true, self::class, 'handle');
        $this->device->scan();
        DbLog::log("ScanDeviceJob completed for device ID {$this->device->id}.", true, self::class, 'handle');
    }
}
