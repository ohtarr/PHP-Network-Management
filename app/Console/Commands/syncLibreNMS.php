<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Sites;
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
    protected $libredevices;
    protected $netboxsites;
    protected $libresitegroups;

    public function handle()
    {
        //print count($this->LibreDevicesToAdd()) . PHP_EOL;
        //print count($this->getNetboxDevices()) . PHP_EOL;
        //print count($this->getLibreDevices()) . PHP_EOL;
        //print count($this->LibreDevicesToRemove()) . PHP_EOL;
        //print_r($this->getLibreDeviceByHostname('khonesdcper01'));
        $this->removeLibreDevices();
        $this->addLibreDevices();

    }

    public function getNetboxDevices()
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

    public function findNetboxDeviceByName($name)
    {
        return $this->getNetboxDevices()->where('hostname', $name)->first();
    }

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
        foreach($this->getNetboxDevices() as $nbdevice)
        {
            unset($libredevice);
            if(isset($nbdevice->virtual_chassis->master->id))
            {
                if($nbdevice->virtual_chassis->master->id != $nbdevice->id)
                {
                    continue;
                }
                $name = $nbdevice->virtual_chassis->name;
            } else {
                $name = $nbdevice->name;
            }
            $libredevice = $this->getLibreDeviceByHostnameFromCache($name);
            if(!$libredevice)
            {
                if($nbdevice->getIpAddress())
                {
                    $toadd[] = $name;
                }
            }
        }
        return $toadd;
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
            foreach($this->getNetboxDevices() as $nbdevice)
            {
                $name = null;
                if(isset($nbdevice->virtual_chassis->name))
                {
                    $name = $nbdevice->virtual_chassis->name;
                } else {
                    $name = $nbdevice->name;
                }
                //print "^" . strtolower($name) . "^ =? ^" . strtolower($libredevice->hostname) . "^" . PHP_EOL;
                if(strtolower($name) == strtolower($libredevice->hostname))
                {
                    $match = $nbdevice;
                    //print_r($match);
                    break;                    
                }
            }
            if(!$match)
            {
                print "NO MATCH FOUND, ADDING TO DELETE PILE!" . PHP_EOL;
                $todelete[] = $libredevice;
            } else {
                if(!$match->getIpAddress())
                {
                    print "MATCH FOUND, but NO IP ADDRESS FOUND in Netbox, ADDING TO DELETE PILE!" . PHP_EOL;
                    $todelete[] = $libredevice;
                }
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
