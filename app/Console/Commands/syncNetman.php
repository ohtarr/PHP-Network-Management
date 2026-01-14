<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\VirtualChassis;
use App\Models\Netbox\VIRTUALIZATION\VirtualMachines;
use App\Models\Device\Device;
use App\Models\Device\Output;

class syncNetman extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:syncNetman';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Netman from Netbox and keep Outputs table clean';

    public $netmandevices;
    public $netboxdevices;
    public $netboxvms;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->addDevices();
        $this->deleteDevices();
        $this->deleteOutputs();
    }

    public function addDevices()
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
            print "********************************************************************" . PHP_EOL;
            print "Fetching Netbox Devices..." . PHP_EOL;
            print "********************************************************************" . PHP_EOL;
            $devices = Devices::where('name__empty','false')->where('cf_NETMAN_MANAGED',"true")->get();
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
            print "********************************************************************" . PHP_EOL;
            print "Fetching Netbox Virtual Machines..." . PHP_EOL;
            print "********************************************************************" . PHP_EOL;
            $nbvms = VirtualMachines::where('name__empty','false')->where('cf_NETMAN_MANAGED',"true")->where('limit','1000')->get();
            $this->netboxvms = $nbvms;
        }
        return $this->netboxvms;
    }

    public function getAllNetmanDevices()
    {
        if(!$this->netmandevices)
        {
            print "********************************************************************" . PHP_EOL;
            print "Fetching Netman Devices..." . PHP_EOL;
            print "********************************************************************" . PHP_EOL;
            $devices = Device::all();
            $this->netmandevices = $devices;
        }
        return $this->netmandevices;
    }

    public function devicesToDelete()
    {
        $delete = [];
        foreach($this->getAllNetmanDevices() as $device)
        {
            $nb = null;
            if($device->netbox_type == Devices::class)
            {
                $nb = $this->getAllNetboxDevices()->where('id', $device->netbox_id)->first();
            }
            if($device->netbox_type == VirtualMachines::class)
            {
                $nb = $this->getNetboxVms()->where('id', $device->netbox_id)->first();
            }
            if(!$nb)
            {
                $delete[] = $device;
            }
        }
        return $delete;
    }

    public function deleteDevices()
    {
        foreach($this->devicesToDelete() as $delete)
        {
            print "********************************************************************" . PHP_EOL;
            print "Deleting Device " . $delete->id . "..." . PHP_EOL;
            $delete->delete();
        }
    }

    public function outputsToDelete()
    {
        $delete = [];
        $outputs = Output::all();
        foreach($outputs as $output)
        {
            if(!isset($output->device_id))
            {
                $delete[] = $output;
                continue;
            }
            $device = $this->getAllNetmanDevices()->where('id', $output->device_id)->first();
            if(!$device)
            {
                $delete[] = $output;
            }
        }
        return $delete;
    }

    public function deleteOutputs()
    {
        foreach($this->outputsToDelete() as $delete)
        {
            print "********************************************************************" . PHP_EOL;
            print "Deleting Output " . $delete->id . "..." . PHP_EOL;
            $delete->delete();
        }
    }
}
