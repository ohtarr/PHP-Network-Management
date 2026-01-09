<?php

namespace App\Console\Commands;

use App\Jobs;
use App\Models\Device\Device;
use App\Jobs\DiscoverDeviceJob;
use Illuminate\Console\Command;

class DiscoverAllDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:discoverAllDevices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover All Devices';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $devices = Device::select('id')->get();
        foreach($devices as $device)
        {
            \Log::info('DiscoverDeviceCommand', ['DiscoverDeviceJob' => 'starting', 'device_id' => $device->id]);   // Log device to the log file.
            DiscoverDeviceJob::dispatch(['id'=>$device->id]);		// Create a scan job for each device in the database
        }
    }

}
