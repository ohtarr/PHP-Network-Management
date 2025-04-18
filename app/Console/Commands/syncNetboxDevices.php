<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Device\Device;

class syncNetboxDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:syncNetboxDevices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Netbox Devices from Netbox to Netman2';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $nbdevices = Devices::all();
        foreach($nbdevices as $nbdevice)
        {
            print "Syncing Device " . $nbdevice->id . "-" . $nbdevice->name . "..." . PHP_EOL;
            $existing = Device::where('netbox_id',$nbdevice->id)->first();
            if($existing)
            {
                print "Device already exists!  Skipping..." . PHP_EOL;
                continue;
            }
            if($nbdevice->getIpAddress())
            {
                print "Device does NOT exist in Netman yet..." . PHP_EOL;
                print "Device has an IP!  Adding to Netman..." . PHP_EOL;

                $dev = new Device;
                $dev->netbox_id = $nbdevice->id;
                $dev->save();
                $dev->fresh()->discover();
                //$dev->fresh()->scan();
                //break;
            }
        }
        return Command::SUCCESS;
    }
}
