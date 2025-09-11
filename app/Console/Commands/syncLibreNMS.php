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
        //print_r($this->findLibreDeviceByName("khonesdcagg01"));
        //print_r($this->getLibreDevices());
        //print_r($this->getNetboxDevices());
        //print count($this->getNetboxDevices()) . PHP_EOL;
        //print count($this->getLibreDevices()) . PHP_EOL;
        //print count($this->getNetboxSites()) . PHP_EOL;
        //print count($this->getLibreNMSSiteGroups()) . PHP_EOL;
        //print count($this->LibreDevicesToAdd()) . PHP_EOL;
        print count($this->LibreDevicesToRemove()) . PHP_EOL;
        //print_r($this->addLibreDevice('khoneldcswa0101'));
        //var_dump($this->deleteLibreDevice('10.251.7.102'));
        //$this->addLibreDevices();
        //$this->removeLibreDevices();
    }

    public function getNetboxDevices()
    {
        if(!$this->netboxdevices)
        {
            $devices = Devices::where('cf_POLLING', 'true')->where('limit','1000')->get();
            foreach($devices as $device)
            {
                if($device->getIpAddress())
                {
                    $toadd[] = $device;
                }
            }
            $this->netboxdevices = collect($toadd);
        }
        return $this->netboxdevices;
    }

    public function getLibreDevices()
    {
        if(!$this->libredevices)
        {
            $this->libredevices = Device::all();
        }
        return $this->libredevices;
    }

    public function getNetboxSites()
    {
        if(!$this->netboxsites)
        {
            $this->netboxsites = Sites::all();
        }
        return $this->netboxsites;
    }

    public function getLibreNMSSiteGroups()
    {
        if(!$this->libresitegroups)
        {
            $this->libresitegroups = DeviceGroup::all();
        }
        return $this->libresitegroups;
    }

    public function findNetboxDeviceByName($name)
    {
        return $this->getNetboxDevices()->where('hostname', $name)->first();
    }

    public function findLibreDeviceBySysname($name)
    {
        return $this->getLibreDevices()->where('sysName', $name)->first();
    }

    public function findLibreDeviceByHostname($name)
    {
        return $this->getLibreDevices()->where('hostname', $name)->first();
    }

    public function deleteLibreDevice($hostname)
    {
        $device = $this->findLibreDeviceByHostname($hostname);
        if(!isset($device->device_id))
        {
            return true;
        }
        try {
            $device->delete();
        } catch (\Exception $e) {
            //print $e->getMessage()."\n";
        }
        $confirm = $this->findLibreDeviceByHostname($hostname);
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
            $vc = $nbdevice->getVirtualChassis();
            if(isset($vc->id))
            {
                $name = $vc->name;
            } else {
                $name = $nbdevice->name;
            }
            //unset($ip);
            //$ip = $nbdevice->getIpAddress();
            $libredevice = $this->findLibreDeviceByHostname($name);
            if(!$libredevice)
            {
                $toadd[] = $name;
            }
        }
        return $toadd;
    }

    public function addLibreDevice($hostname)
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
    }

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
        $libredevices = $this->getLibreDevices();
        foreach($libredevices as $libredevice)
        {
            print "*************************************************" . PHP_EOL;
            print "Processing libreNMS Device {$libredevice->hostname}..." . PHP_EOL;
            $match = null;
            foreach($this->getNetboxDevices() as $nbdevice)
            {
                //print_r($nbdevice);
                $vc = $nbdevice->getVirtualChassis();
                if(isset($vc->id))
                {
                    $name = $vc->name;
                } else {
                    $name = $nbdevice->name;
                }
                //print strtolower($name) . " =? " . strtolower($libredevice->hostname) . PHP_EOL;
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
            $confirm = $this->findLibreDeviceByHostname($libredevice->hostname);
            if(!isset($confirm->device_id))
            {
                return true;
            } else {
                return false;
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
