<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device\Device;
use App\Jobs\ScanDeviceJob;

class ScanDevice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:scanDevice {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan Device by ID';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $arguments = $this->arguments();
        $id = $arguments['id'];
        if(!$id)
        {
            throw new \Exception('No ID specified!');
        }

        \Log::info('ScanDeviceCommand', ['ScanDeviceJob' => 'starting', 'device_id' => $id]);   // Log device to the log file.
        $result = ScanDeviceJob::dispatch($id);		// Create a scan job for each device in the database
        return $result;
    }
}
