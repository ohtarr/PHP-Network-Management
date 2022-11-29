<?php

namespace App\Console\Commands;

use App\Jobs;
use App\Device\Device;
use App\Jobs\DiscoverDeviceJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AutoDiscoverDevice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:autoDiscoverDevice {--ip=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto Device Discover device by ip address.';

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
        //$options = $this->options();
        if(!$this->option('ip'))
        {
            throw new \Exception('No IP specified!');
        }
        $status = Cache::store('cache_discovery')->get($this->option('ip'));
        print "status: " . $status . "\n";
        if($status === "0" || $status === "1")
        {
            return null;
        }
        Cache::store('cache_discovery')->put($this->option('ip'),0,120);

        $device = new Device(['ip' => $this->option('ip')]);
        if($device->deviceExists())
        {
            return null;
        }

        Log::info('DiscoverDeviceCommand', ['DiscoverDeviceJob' => 'starting', 'device_ip' => $this->option('ip')]);   // Log device to the log file.
        $result = DiscoverDeviceJob::dispatch(['ip' => $this->option('ip')]);           // Create a scan job for each device in the database
        return $result;
    }

}