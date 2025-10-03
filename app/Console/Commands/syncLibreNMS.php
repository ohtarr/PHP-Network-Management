<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\DCIM\VirtualChassis;
use App\Models\Netbox\VIRTUALIZATION\VirtualMachines;
use App\Models\LibreNMS\Device;
use App\Models\LibreNMS\DeviceGroup;

class syncLibreNMS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:syncLibreNMS';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync LibreNMS Devices and DeviceGroups';

    /**
     * Execute the console command.
     *
     * @return int
     */

    protected $netboxdevices;
    protected $netboxvcs;
    protected $libredevices;
    protected $netboxsites;
    protected $libresitegroups;

    public function handle()
    {
        //print_r($this->getNetboxDevices2());
        //print count($this->LibreDevicesToAdd()) . PHP_EOL;
        //print_r($this->LibreDevicesToAdd());
        //print count($this->getNetboxDevices()) . PHP_EOL;
        //print count($this->getLibreDevices()) . PHP_EOL;
        //print count($this->LibreDevicesToRemove()) . PHP_EOL;
        //print_r($this->LibreDevicesToRemove());
        //print_r($this->getLibreDeviceByHostname('khonesdcper01'));
        $this->removeLibreDevices();
        $this->addLibreDevices();

    }

    public function getNetboxDevices3()
    {
        if(!$this->netboxdevices)
        {
            print "Fetching Netbox Devices!" . PHP_EOL;
            $devices = Devices::where('cf_POLLING', 'true')->where('name__empty','false')->where('limit','9999')->get();
            foreach($devices as $device)
            {
                //if($device->getIpAddress())
                //{
                    $toadd[] = $device;
                //}
            }
            $this->netboxdevices = collect($toadd);
        }
        return $this->netboxdevices;
    }

    public function getNetboxVcs()
    {
        if(!$this->netboxvcs)
        {
            print "Fetching Netbox VirtualChassis!" . PHP_EOL;
            $virtualchassis = [];
            $vcs = VirtualChassis::where('name__empty','false')->where('limit','1000')->get();
            $this->netboxvcs = $vcs;
        }
        return $this->netboxvcs;
    }

    public function getNetboxDevices()
    {
        if(!$this->netboxdevices)
        {
            print "Fetching Netbox Devices!" . PHP_EOL;
            $nbdevices = [];
            $devices = Devices::where('cf_POLLING', 'true')->where('virtual_chassis_member', 'false')->where('name__empty','false')->where('limit','9999')->get();
            foreach($devices as $device)
            {
                $nbdevices[] = $device->generateDnsName();
            }
            $devices = Devices::where('cf_POLLING', 'true')->where('virtual_chassis_member', 'true')->where('name__empty','false')->where('limit','9999')->get();
            foreach($devices as $device)
            {
                if(isset($device->virtual_chassis->master->id) && $device->virtual_chassis->master->id == $device->id)
                {
                    $nbdevices[] = $this->getNetboxVcs()->where('id', $device->virtual_chassis->id)->first()->generateDnsName();
                }
            }
            $vms = VirtualMachines::where('cf_POLLING', 'true')->where('name__empty','false')->where('limit','1000')->get();
            foreach($vms as $vm)
            {
                $nbdevices[] = $vm->generateDnsName();
            }            
            $this->netboxdevices = collect($nbdevices);
            print "Generated " . count($this->netboxdevices) . " devices from Netbox." . PHP_EOL;
        }
        return $this->netboxdevices;
    }

    public function getLibreDevices()
    {
        if(!$this->libredevices)
        {
            print "Fetching LibreNMS Devices!" . PHP_EOL;
            $this->libredevices = Device::all();
        }
        return $this->libredevices;
    }

    public function getNetboxSites()
    {
        if(!$this->netboxsites)
        {
            print "Fetching Netbox Sites!" . PHP_EOL;
            $this->netboxsites = Sites::all();
        }
        return $this->netboxsites;
    }

    public function getLibreNMSSiteGroups()
    {
        if(!$this->libresitegroups)
        {
            print "Fetching LibreNMS Device Groups!" . PHP_EOL;
            $this->libresitegroups = DeviceGroup::all();
        }
        return $this->libresitegroups;
    }

    /*
    public function findNetboxDeviceByName($name)
    {
        return $this->getNetboxDevices()->where('hostname', $name)->first();
    }
*/
/*     public function findLibreDeviceBySysname($name)
    {
        return $this->getLibreDevices()->where('sysName', $name)->first();
    } */

    public function getLibreDeviceByHostname($name)
    {
        return Device::find($name);
    }

    public function getLibreDeviceByHostnameFromCache($name)
    {
        return $this->getLibreDevices()->where('hostname', $name)->first();
    }

    public function deleteLibreDevice($hostname)
    {
        $device = $this->getLibreDeviceByHostname($hostname);
        if(!isset($device->device_id))
        {
            return true;
        }
        try {
            $device->delete();
        } catch (\Exception $e) {
            //print $e->getMessage()."\n";
        }
        $confirm = $this->getLibreDeviceByHostname($hostname);
        if(!isset($confirm->device_id))
        {
            return true;
        } else {
            return false;
        }
    }

    public function LibreDevicesToAdd()
    {
        foreach($this->getNetboxDevices() as $nbdevicename)
        {
            unset($libredevice);
             $libredevice = $this->getLibreDeviceByHostnameFromCache($nbdevicename);
            if(!$libredevice)
            {
                $toadd[] = $nbdevicename;
            }
        }
        return collect($toadd);
    }

/*     public function addLibreDevice($hostname)
    {
        try{
            $device = Device::addByHostname($hostname);
        } catch (\Exception $e) {
            //print $e->getMessage()."\n";
        }

        if(isset($device->device_id))
        {
            return $device;
        }
    } */

    public function addLibreDevices()
    {
        $toadd = $this->LibreDevicesToAdd();
        foreach($toadd as $name)
        {
            //unset($ip);
            unset($device);
            print "*************************************************" . PHP_EOL;
            print "Attempting to add device {$name}" . PHP_EOL;
            //$ip = $nbdevice->getIpAddress();
            //if(!$ip)
            //{
            //    print "NO IP found, skipping..." . PHP_EOL;
            //    continue;
            //}
            //print "IP {$ip}" . PHP_EOL;
            try{
                $device = Device::addByHostname($name);
            } catch (\Exception $e) {
                //print $e->getMessage()."\n";
            }

            if(isset($device->device_id))
            {
                print "Device added successfully!" . PHP_EOL;
            } else {
                print "Device failed to add!" . PHP_EOL;
            }
        }
    }

    public function LibreDevicesToRemove()
    {
        $todelete = [];
        $libredevices = $this->getLibreDevices();
        foreach($libredevices as $libredevice)
        {
            print "*************************************************" . PHP_EOL;
            print "Processing libreNMS Device {$libredevice->hostname}..." . PHP_EOL;
            $match = null;
            foreach($this->getNetboxDevices() as $nbdevicename)
            {
                //print "^" . strtolower($nbdevicename) . "^ =? ^" . strtolower($libredevice->hostname) . "^" . PHP_EOL;
                if(strtolower($nbdevicename) == strtolower($libredevice->hostname))
                {
                    $match = $nbdevicename;
                    //print_r($match);
                    break;                    
                }
            }
            if(!$match)
            {
                print "NO MATCH FOUND, ADDING TO DELETE PILE!" . PHP_EOL;
                $todelete[] = $libredevice;
            }
        }
        return collect($todelete);
    }

    public function removeLibreDevices()
    {
        $toremove = $this->LibreDevicesToRemove();
        foreach($toremove as $libredevice)
        {
            print "*************************************************" . PHP_EOL;
            print "Attempting to REMOVE device {$libredevice->hostname} - {$libredevice->sysName}" . PHP_EOL;
            try{
                $libredevice->delete();
            } catch (\Exception $e) {
                //print $e->getMessage()."\n";
            }
        }
    }

    public function LibreSitGroupsToAdd()
    {

    }

    public function LibreSiteGroupsToRemove()
    {

    }
}
