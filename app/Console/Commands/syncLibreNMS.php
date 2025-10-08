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
        $this->deleteLibreSiteGroups();
        $this->addLibreSiteGroups();
        $this->ignoreLibreDevices();
        $this->alertLibreDevices();
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

    public function getNetboxDevice2()
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

    public function getNetboxDevices()
    {
        if(!$this->netboxdevices)
        {
            print "Fetching Netbox Devices!" . PHP_EOL;
            $nbdevices = [];
            $devices = Devices::where('cf_POLLING', 'true')->where('virtual_chassis_member', 'false')->where('name__empty','false')->where('limit','9999')->get();
            foreach($devices as $device)
            {
                $nbdevices[] = $device;
            }
            $devices = Devices::where('cf_POLLING', 'true')->where('virtual_chassis_member', 'true')->where('name__empty','false')->where('limit','9999')->get();
            foreach($devices as $device)
            {
                if(isset($device->virtual_chassis->master->id) && $device->virtual_chassis->master->id == $device->id)
                {
                    $nbdevices[] = $device;
                }
            }
            $vms = VirtualMachines::where('cf_POLLING', 'true')->where('name__empty','false')->where('limit','1000')->get();
            foreach($vms as $vm)
            {
                $nbdevices[] = $vm;
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

    public function getLibreSiteGroups()
    {
        $sitegroups = [];
        if(!$this->libresitegroups)
        {
            print "Fetching LibreNMS Device Groups!" . PHP_EOL;
            try {
                $devicegroups = DeviceGroup::all();
            } catch (\Exception $e) {
                print $e->getMessage()."\n";
            }
            if(!isset($devicegroups))
            {
                $this->libresitegroups = collect([]);
                return $this->libresitegroups;
            }
            foreach($devicegroups as $devicegroup)
            {
                if(substr($devicegroup->name, 0, 5) == "SITE_")
                {
                    $sitegroups[] = $devicegroup;
                }
            }
            $this->libresitegroups = collect($sitegroups);
        }
        return $this->libresitegroups;
    }

    public function libreGroupsToAdd()
    {
        $toadd = [];
        foreach($this->getNetboxSites() as $nbsite)
        {
            $match = null;
            $match = $this->getLibreSiteGroups()->where('name', 'SITE_' . $nbsite->name)->first();
            if(!$match)
            {
                $toadd[] = $nbsite->name;
            }
        }
        return $toadd;
    }

    public function addLibreSiteGroups()
    {
        foreach($this->libreGroupsToAdd() as $sitecode)
        {
            print "ADDING SITE_GROUP for site {$sitecode}..." . PHP_EOL;
            try {
                DeviceGroup::createSiteGroup($sitecode);
            } catch (\Exception $e) {
                print $e->getMessage()."\n";
                continue;
            }
        }
    }

    public function libreGroupsToDelete()
    {
        $todelete = [];
        foreach($this->getLibreSiteGroups() as $libregroup)
        {
            $sitecode = str_replace("SITE_", "", $libregroup->name);
            $match = null;
            $match = $this->getNetboxSites()->where('name', $sitecode)->first();
            if(!$match)
            {
                $todelete[] = $libregroup;
            }
        }
        return $todelete;
    }

    public function deleteLibreSiteGroups()
    {
        foreach($this->libreGroupsToDelete() as $libresitegroup)
        {
            print "DELETING SITE_GROUP {$libresitegroup->name}..." . PHP_EOL;
            try {
                $libresitegroup->delete();
            } catch (\Exception $e) {
                print $e->getMessage()."\n";
                continue;
            }
        }
    }


/* {
	"name":"SITE_KHONELDC",
	"desc":"KHONELDC",
	"type":"dynamic",
  "rules": "{\"condition\":\"AND\",\"rules\":[{\"id\":\"devices.sysName\",\"field\":\"devices.sysName\",\"type\":\"string\",\"input\":\"text\",\"operator\":\"begins_with\",\"value\":\"KHONELDC\"}],\"valid\":true,\"joins\":[]}"
} */

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

    public function getNetboxDeviceByName($name)
    {
        return $this->getNetboxDevices()->where('name', $name)->first();
    }

    public function getNetboxDeviceByNameCaseInsensitive($name)
    {
        foreach($this->getNetboxDevices() as $nbdevice)
        {
            if(!isset($nbdevice->name))
            {
                continue;
            }
            if(strtolower($nbdevice->name) == strtolower($name))
            {
                return $nbdevice;
            }
        }

    }

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
            if(!isset($nbdevice->name))
            {
                continue;
            }
            $libredevice = $this->getLibreDeviceByHostnameFromCache($nbdevice->name);
            if(!$libredevice)
            {
                $toadd[] = $nbdevice->name;
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
            foreach($this->getNetboxDevices() as $nbdevice)
            {
                if(!isset($nbdevice->name))
                {
                    continue;
                }
                //print "^" . strtolower($nbdevicename) . "^ =? ^" . strtolower($libredevice->hostname) . "^" . PHP_EOL;
                if(strtolower($nbdevice->name) == strtolower($libredevice->hostname))
                {
                    $match = $nbdevice->name;
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

    public function LibreDevicesToIgnore()
    {
        $toignore = [];
        foreach($this->getLibreDevices() as $libredevice)
        {
            //print "*************************************************" . PHP_EOL;
            //print "Processing libreNMS Device {$libredevice->hostname}..." . PHP_EOL;
            $match = null;
            $match = $this->getNetboxDeviceByNameCaseInsensitive($libredevice->hostname);
            if($match)
            {
                if(isset($match->custom_fields->ALERT) && $match->custom_fields->ALERT == false)
                {
                    if($libredevice->ignore == 0)
                    {
                        $toignore[] = $libredevice;
                    }
                }
            }
        }
        return collect($toignore);
    }

    public function LibreDevicesToAlert()
    {
        $toalert = [];
        foreach($this->getLibreDevices() as $libredevice)
        {
            //print "*************************************************" . PHP_EOL;
            //print "Processing libreNMS Device {$libredevice->hostname}..." . PHP_EOL;
            $match = null;
            $match = $this->getNetboxDeviceByNameCaseInsensitive($libredevice->hostname);
            if($match)
            {
                if(isset($match->custom_fields->ALERT) && $match->custom_fields->ALERT == true)
                {
                    if($libredevice->ignore == 1)
                    {
                        $toalert[] = $libredevice;
                    }
                }
            }
        }
        return collect($toalert);
    }

    public function ignoreLibreDevices()
    {
        foreach($this->LibreDevicesToIgnore() as $libredevice)
        {
            print "IGNORING device {$libredevice->hostname} ..." . PHP_EOL;
            try {
                $libredevice->disableAlerting();
            } catch (\Exception $e) {
                print $e->getMessage()."\n";
                continue;
            }
        }
    }

    public function alertLibreDevices()
    {
        foreach($this->LibreDevicesToAlert() as $libredevice)
        {
            print "Enabling ALERTING on device {$libredevice->hostname} ..." . PHP_EOL;
            try {
                $libredevice->enableAlerting();
            } catch (\Exception $e) {
                print $e->getMessage()."\n";
                continue;
            }
        }
    }
}
