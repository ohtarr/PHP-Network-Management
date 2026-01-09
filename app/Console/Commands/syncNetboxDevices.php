<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\VirtualChassis;
use App\Models\Netbox\VIRTUALIZATION\VirtualMachines;
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

    public $netboxdevices;
    public $netboxvms;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->syncDevices();
    }

    public function old()
    {
        $nbdevices = Devices::all();
        foreach($nbdevices as $nbdevice)
        {
            print "Syncing Device " . $nbdevice->id . "-" . $nbdevice->name . "..." . PHP_EOL;
            $existing = Device::where('netbox_id', $nbdevice->id)->first();
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
                try{
                    $dev->fresh()->discover();
                } catch (\Exception $e) {

                }
            }
        }
        return Command::SUCCESS;
    }

    public function syncDevices()
    {
        $vc = $this->getNetboxVcMasterDevices();
        $devices = $this->getNetboxNonVcDevices();
        $vms = $this->getNetboxVms();

        $merged = $vc->merge($devices);
        $merged = $merged->merge($vms);

        foreach($merged as $nbdevice)
        {
            print "********************************************************************" . PHP_EOL;
            print "Syncing Device " . $nbdevice->id . "-" . $nbdevice->name . "..." . PHP_EOL;
            $existing = Device::where('netbox_id', $nbdevice->id)->first();
            if($existing)
            {
                print "Device already exists!  Skipping..." . PHP_EOL;
                continue;
            }
            print "Device does NOT exist in Netman yet..." . PHP_EOL;
            if($nbdevice->getIpAddress())
            {
                print "Device has an IP!  Adding to Netman..." . PHP_EOL;
                $dev = new Device;
                $dev->netbox_type = $nbdevice::class;
                $dev->netbox_id = $nbdevice->id;
                $dev->save();
                try{
                    //$dev->fresh()->discover();
                } catch (\Exception $e) {

                }
            }
        }

    }

    public function getAllNetboxDevices()
    {
        if(!$this->netboxdevices)
        {
            $devices = Devices::where('name__empty','false')->get();
            $this->netboxdevices = $devices;
        }
        return $this->netboxdevices;
    }

    public function getNetboxVcMasterDevices()
    {
        $vcmasters = [];
        foreach($this->getAllNetboxDevices() as $nbdevice)
        {
            if(isset($nbdevice->virtual_chassis->master->id) && $nbdevice->virtual_chassis->master->id == $nbdevice->id)
            {
                $vcmasters[] = $nbdevice;
            }
        }
        return collect($vcmasters);
    }

    public function getNetboxNonVcDevices()
    {
        $devices = [];
        foreach($this->getAllNetboxDevices() as $nbdevice)
        {
            if(!$nbdevice->virtual_chassis)
            {
                $devices[] = $nbdevice;
            }
        }
        return collect($devices);
    }

    public function getNetboxVms()
    {
        $netboxvms = [];
        if(!$this->netboxvms)
        {
            $nbvms = VirtualMachines::where('name__empty','false')->where('limit','1000')->get();
            $this->netboxvms = $nbvms;
        }
        return $this->netboxvms;
    }

}
