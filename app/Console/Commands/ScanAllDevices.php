<?php

namespace App\Console\Commands;

use App\Jobs;
use App\Models\Device\Device;
use App\Jobs\ScanDeviceJob;
use Illuminate\Console\Command;

class ScanAllDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:scanAllDevices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan All Devices';

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
            \Log::info('ScanDeviceCommand', ['ScanDeviceJob' => 'starting', 'device_id' => $device->id]);   // Log device to the log file.
            ScanDeviceJob::dispatch($device->id);		// Create a scan job for each device in the database
        }
    }

}
