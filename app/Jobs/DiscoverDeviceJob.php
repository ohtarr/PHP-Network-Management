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
            "No ID found!  Cancelling Job!\n";
        }
        Log::info(__FILE__, ['function' => __FUNCTION__, 'state' => 'starting', 'id' => $device->id]);   // Log device to the log file.
        $device->discover();
        Log::info(__FILE__, ['function' => __FUNCTION__, 'state' => 'complete', 'id' => $device->id]);   // Log device to the log file.
    }
}
