<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\DCIM\VirtualChassis;
use App\Models\Netbox\DCIM\Manufacturers;
use App\Models\Netbox\VIRTUALIZATION\VirtualMachines;
use App\Models\LibreNMS\Device;
use App\Models\LibreNMS\DeviceGroup;
use App\Models\LibreNMS\Location;
use JJG\Ping;

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

    //protected $netboxdevices;
    protected $netboxnonvcdevices;
    //protected $netboxvcs;
    protected $netboxvcmasters;
    protected $netboxvms;
    protected $netboxall;
    protected $libredevices;
    protected $netboxsites;
    protected $libresitegroups;
    protected $opengears;
    protected $librelocations;

    public function handle()
    {
        $this->ignoreLibreDevices();
        $this->alertLibreDevices();
        $this->removeLibreDevices();
        $this->addLibreDevices();
        $this->addLibreLocations();
        $this->updateLibreLocations();
        $this->updateDeviceLocations();
        //$this->deleteLocations();
    }

    public function ping($hostname, $timeout = 5)
	{
		$PING = new Ping($hostname);
        $PING->setTimeout($timeout);
		$LATENCY = $PING->ping();
		if (!$LATENCY)
		{
			return false;
		}else{
			return $LATENCY;
		}
	}

    public function getNetboxVcMasterDevices()
    {
        $vcmasters = [];
        if(!$this->netboxvcmasters)
        {
            print "Fetching Netbox VirtualChassis Masters!" . PHP_EOL;
            $vcmembers = Devices::where('cf_POLLING', 'true')->where('virtual_chassis_member', 'true')->where('name__empty','false')->where('limit','9999')->get();           
            foreach($vcmembers as $vcmember)
            {
                if(isset($vcmember->virtual_chassis->master->id) && $vcmember->virtual_chassis->master->id == $vcmember->id)
                {
                    $vcmember->generated = $vcmember->generateDnsName();
                    $vcmasters[] = $vcmember;
                    continue;
                }
            }
            $this->netboxvcmasters = collect($vcmasters);
        }
        return $this->netboxvcmasters;
    }

    public function getNetboxNonVcDevices()
    {
        $nbnonvcdevices = [];
        if(!$this->netboxnonvcdevices)
        {
            print "Fetching Netbox NON-Virtual-Chassis Devices!" . PHP_EOL;
            $nbdevices = Devices::where('cf_POLLING', 'true')->where('virtual_chassis_member', 'false')->where('name__empty','false')->where('limit','9999')->get();
            foreach($nbdevices as $nbdevice)
            {
                $nbdevice->generated = $nbdevice->generateDnsName();
                $nbnonvcdevices[] = $nbdevice;
            }
            $this->netboxnonvcdevices = collect($nbnonvcdevices);
        }
        return $this->netboxnonvcdevices;
    }

    public function getNetboxVms()
    {
        $netboxvms = [];
        if(!$this->netboxvms)
        {
            print "Fetching Netbox VirtualMachines!" . PHP_EOL;
            $nbvms = VirtualMachines::where('cf_POLLING', 'true')->where('name__empty','false')->where('limit','1000')->get();
            foreach($nbvms as $nbvm)
            {
                $nbvm->generated = $nbvm->generateDnsName();
                $netboxvms[] = $nbvm;
            }
            $this->netboxvms = collect($netboxvms);
        }
        return $this->netboxvms;
    }

    public function getNetboxOpengears()
    {
        if(!$this->opengears)
        {
            print "Fetching Netbox Opengear Devices!" . PHP_EOL;
            $opengears = [];
            $manufacturer = Manufacturers::where('name','Opengear')->first();
            if(!isset($manufacturer->id))
            {
                return null;
            }
            $nbdevices = Devices::where('cf_POLLING', 'true')->where('manufacturer_id', $manufacturer->id)->where('name__empty','false')->where('limit','9999')->get();
            foreach($nbdevices as $nbdevice)
            {
                $nbdevice->generated = $nbdevice->generateDnsName() . "-oob";
                $nbdevice->icmponly = true;
                $opengears[] = $nbdevice;
            }
            $this->opengears = collect($opengears);
        }
        return $this->opengears;
    }

    public function getAllNetbox()
    {
        if(!$this->netboxall)
        {
            $nball = [];
            foreach($this->getNetboxNonVcDevices() as $nbdevice)
            {
                //$nbdevice->generated = $nbdevice->generateDnsName();
                $nball[] = $nbdevice;
            }
            foreach($this->getNetboxVcMasterDevices() as $nbvcmaster)
            {
                //$nbvcmaster->generated = $nbvcmaster->generateDnsName();
                $nball[] = $nbvcmaster;
            }
            foreach($this->getNetboxVms() as $nbvm)
            {
                //$nbvm->generated = $nbvm->generateDnsName();
                $nball[] = $nbvm;
            }
            foreach($this->getNetboxOpengears() as $og)
            {
                //$nbvm->generated = $nbvm->generateDnsName();
                $nball[] = $og;
            }
            $this->netboxall = collect($nball);
        }
        return $this->netboxall;
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

    public function getLibreLocations()
    {
        if(!$this->librelocations)
        {
            print "Fetching LibreNMS Locations!" . PHP_EOL;
            $this->librelocations = Location::all();
        }
        return $this->librelocations;
    }

    public function libreLocationsToAdd()
    {
        $toadd = [];
        foreach($this->getNetboxSites() as $nbsite)
        {
            $match = $this->getLibreLocations()->where('location', $nbsite->name)->first();
            if(!$match)
            {
                $toadd[] = $nbsite;
            }
        }
        return $toadd;
    }

    public function addLibreLocations()
    {
        foreach($this->libreLocationsToAdd() as $nbsite)
        {
            print "ADDING LibreNMS Location for site {$nbsite->name}..." . PHP_EOL;
            $params = [
                'location'  =>  $nbsite->name,
                'lat'       =>  $nbsite->latitude,
                'lng'       =>  $nbsite->longitude,
            ];
            try {
                Location::create($params);
            } catch (\Exception $e) {
                print $e->getMessage()."\n";
                continue;
            }
        }
    }

    public function libreLocationsToUpdate()
    {
        $toupdate = [];
        foreach($this->getLibreLocations() as $loc)
        {
            $tmp = [];
            $nbsite = $this->getNetboxSites()->where('name', $loc->location)->first();
            if($nbsite)
            {
                if($nbsite->latitude != $loc->lat || $nbsite->longitude != $loc->lng)
                {
                    $tmp['loc'] = $loc;
                    $tmp['params']['lat'] = $nbsite->latitude;
                    $tmp['params']['lng'] = $nbsite->longitude;
                    $toupdate[] = $tmp;
                }
            }
        }
        return $toupdate;
    }

    public function updateLibreLocations()
    {
        foreach($this->libreLocationsToUpdate() as $update)
        {
            unset($updated);
            print "Updating LibreNMS Location {$update['loc']->location}..." . PHP_EOL;
            $updated = $update['loc']->update($update['params']);
            if($update)
            {
                print "Successfully updated Location {$update['loc']->location}..." . PHP_EOL;
            } else {
                print "Failed to update Location {$update['loc']->location}..." . PHP_EOL;
            }
        }
    }

    public function getNetboxDeviceByNameCaseInsensitive($name)
    {
        foreach($this->getAllNetbox() as $nbdevice)
        {
            if(!isset($nbdevice->generated))
            {
                continue;
            }
            if(strtolower($nbdevice->generated) == strtolower($name))
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
        //return $this->getLibreDevices()->where('hostname', $name)->first();
        $results = $this->getLibreDevices()->filter(function ($item) use ($name){
            return strtolower($item->hostname) == strtolower($name);
        });
        return $results->first();
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
        $toadd = [];
        foreach($this->getAllNetbox() as $nbdevice)
        {
            unset($libredevice);
            if(!isset($nbdevice->generated))
            {
                continue;
            }
            $libredevice = $this->getLibreDeviceByHostnameFromCache($nbdevice->generated);
            if(!$libredevice)
            {
                //$toadd[] = $nbdevice->generated;
                $toadd[] = $nbdevice;
            }
        }
        return collect($toadd);
    }

    public function addLibreDevices()
    {
        $toadd = $this->LibreDevicesToAdd();
        foreach($toadd as $nbdevice)
        {
            unset($device);
            unset($ping);
            print "*************************************************" . PHP_EOL;
            print "Attempting to add device {$nbdevice->generated}" . PHP_EOL;
            $ping = $this->ping($nbdevice->generated);
            if(!$ping)
            {
                print "Device failed to ping, skipping..." . PHP_EOL;
                continue;
            }
            $body = [
                'hostname'              =>  $nbdevice->generated,
            ];
            if(isset($nbdevice->site->name))
            {
                $body['location'] = $nbdevice->site->name;
                $body['override_sysLocation'] = 1;                
            }
            if(isset($nbdevice->icmponly))
            {
                $body['snmp_disable'] = true;
                $body['force_add'] = true;
            }
            try{
                $device = Device::create($body);
            } catch (\Exception $e) {
                print $e->getMessage()."\n";
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
            //print "*************************************************" . PHP_EOL;
            //print "Processing libreNMS Device {$libredevice->hostname}..." . PHP_EOL;
            $match = null;
            foreach($this->getAllNetbox() as $nbdevice)
            {
                if(!isset($nbdevice->generated))
                {
                    continue;
                }
                //print "^" . strtolower($nbdevicename) . "^ =? ^" . strtolower($libredevice->hostname) . "^" . PHP_EOL;
                if(strtolower($nbdevice->generated) == strtolower($libredevice->hostname))
                {
                    $match = $nbdevice->generated;
                    //print_r($match);
                    break;
                }
            }
            if(!$match)
            {
                //print "NO MATCH FOUND, ADDING TO DELETE PILE!" . PHP_EOL;
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

    public function updateDeviceLocations()
    {
        print "Updating Device Locations" . PHP_EOL;
        $libredevices = $this->getLibreDevices();
        foreach($libredevices as $libredevice)
        {
            unset($loc);
            unset($nbdevice);
            print "***********************************" . PHP_EOL;
            print $libredevice->hostname . PHP_EOL;
            print "***********************************" . PHP_EOL;
            $nbdevice = $this->getNetboxDeviceByNameCaseInsensitive($libredevice->hostname);
            if(!$nbdevice)
            {
                print "No Netbox Device found, skipping..." . PHP_EOL;
                continue;
            }
            print "Found Netbox Device {$nbdevice->name} with ID {$nbdevice->id}..." . PHP_EOL;
            if(isset($nbdevice->site->name))
            {
                $loc = $this->getLibreLocations()->where('location', $nbdevice->site->name)->first();
            }
            if(!isset($loc->location))
            {
                print "No LibreNMS Location found, skipping..." . PHP_EOL;
                continue;
            }
            if($libredevice->location_id != $loc->id)
            {
                print "Location needs to be updated!" . PHP_EOL;
                $params = [
                    'field' =>  ['location_id', 'override_sysLocation'],
                    'data'  =>  [$loc->id, 1],
                ];
                print_r($params);
                $libredevice->update($params);
            } else {
                print "Location is correct... skipping..." . PHP_EOL;
            }
        }
    }

    public function locationsToDelete()
    {
        $delete = [];
        $libredevices = $this->getLibreDevices();
        $locs = $this->getLibreLocations();
        foreach($locs as $loc)
        {
            $match = null;
            foreach($libredevices as $device)
            {
                if($device->location_id == $loc->id)
                {
                    $match = $device;
                    break;
                }
            }
            if(!$match)
            {
                $delete[] = $loc;
            }
        }
        return $delete;
    }

    public function deleteLocations()
    {
        foreach($this->locationsToDelete() as $loc)
        {
            $loc->delete();
        }
    }
}
