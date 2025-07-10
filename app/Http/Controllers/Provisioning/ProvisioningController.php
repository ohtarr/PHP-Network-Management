<?php

namespace App\Http\Controllers\Provisioning;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\DCIM\Locations;
use App\Models\Netbox\IPAM\AsnRanges;
use App\Models\Netbox\IPAM\Asns;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\IPAM\Roles;
use App\Models\Netbox\DCIM\DeviceTypes;
use App\Models\ServiceNow\Location;
use App\Models\Mist\Site;

class ProvisioningController extends Controller
{
    public $logs = [];

    public function __construct()
    {
	    $this->middleware('auth:api');
    }

    public function addLog($status, $msg)
    {
        $this->logs[] = [
            'status'    =>  $status,
            'msg'       =>  $msg,
        ];
    }

    public function getSnowLocations()
    {
        $totalstatus = 1;
        $locs = Location::where('companyISNOTEMPTY')->where('u_network_mob_dateISEMPTY')->where('u_network_demob_dateISEMPTY')->get();
        if(!$locs)
        {
            $this->addLog(0, "Unable to find valid SNOW location.");
            $totalstatus = 0;

            $return['status'] = $totalstatus;
            $return['log'] = $this->logs;
            return json_encode($return);
        }
        foreach($locs as $loc)
        {
            if($loc['name'])
            {
                $sitecodes[] = $loc['name'];
            }
        }
        sort($sitecodes);
        $this->addLog(1, "SNOW LOCATIONS successfully retreived.");
        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = $sitecodes;
        return json_encode($return);
    }

    public function getSnowLocation($sitecode)
    {
        $loc = Location::where('companyISNOTEMPTY')->where('name', $sitecode)->get();
        if($loc)
        {
            $this->addLog(1, "SNOW LOCATION successfully retreived.");
            $return['status'] = 1;
            $return['log'] = $this->logs;
            $return['data'] = $loc;
            return json_encode($return);
        } else {
            $this->addLog(0, "SNOW LOCATION was not found.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            return json_encode($return);
        }
    }

    public function getNetboxSite($sitecode)
    {
        $site = Sites::where('name__ie', $sitecode)->first();
        return json_encode($site);
    }

    public function deployNetboxSite(Request $request, $sitecode)
    {
        $totalstatus = 1;
        //Attempt to get existing snow location.
        $snowloc = Location::where('companyISNOTEMPTY')->where('name', $sitecode)->first();
        if(!$snowloc)
        {
            $totalstatus = 0;
            $return['status'] = $totalstatus;
            $this->addLog(0, "Unable to find valid SNOW location.");
            $return['log'] = $this->logs;
            return json_encode($return);
        } else {
            $this->addLog(1, "Found SNOW location ID {$snowloc->sys_id}.");
        }

        //Attempt to get existing netbox site.
        $netboxsite = Sites::where('name__ie', $sitecode)->first();
        if(isset($netboxsite->id))
        {
            $this->addLog(1, "Netbox SITE ID {$netboxsite->id} already exists.");
        } else {
            $netboxsite = $snowloc->createNetboxSite();
            if(isset($netboxsite->id))
            {
                $this->addLog(1, "Created Netbox SITE ID {$netboxsite->id}.");
            } else {
                $totalstatus = 0;
                $this->addLog(0, "Failed to create new Netbox SITE.");
                $return['status'] = $totalstatus;
                $return['log'] = $this->logs;
                return json_encode($return);
            }
        }

        //Attempt to get existing ASN, if none create new.
        $asns = $netboxsite->getAsns();
        if(isset($asns->first()->asn))
        {
            $this->addLog(1, "ASN {$asns->first()->asn} already exists..");
        } else {
            //create ASN
            $newasn = Asns::getNextAvailable();
            if(isset($newasn))
            {
                $this->addLog(1, "Available ASN {$newasn} has been found.");
                $asn = Asns::where('asn',$newasn)->first();
                if(!isset($asn->id))
                {
                    $params = [
                        'asn'   =>  $newasn,
                        'rir'   =>  1,
                    ];
                    $asn = Asns::create($params);
                    if(isset($asn->id))
                    {
                        $this->addLog(1, "Created new ASN ID {$asn->id}.");
                        $params = [
                            'asns'  =>  [$asn->id],
                        ];
                        $netboxsite = $netboxsite->update($params);
                        if(isset($netboxsite->id))
                        {
                            $this->addLog(1, "Assigned ASN ID {$asn->id} to site.");
                        } else {
                            $totalstatus = 0;
                            $this->addLog(0, "Failed to assign ASN ID {$asn->id} to site.");
                            $return['status'] = $totalstatus;
                            $return['log'] = $this->logs;
                            return $return;                            
                        }
                    } else {
                        $totalstatus = 0;
                        $this->addLog(0, "Failed to find a new ASN.");
                        $return['status'] = $totalstatus;
                        $return['log'] = $this->logs;
                        return $return;
                    }
                }
            } else {
                $totalstatus = 0;
                $this->addLog(0, "Failed to find a new ASN.");
                $return['status'] = $totalstatus;
                $return['log'] = $this->logs;
                return $return;
            }
        }

        $siteprovsupernet = $netboxsite->getProvisioningSupernet();
        if(isset($siteprovsupernet->id))
        {
            $this->addLog(1, "SUPERNET {$siteprovsupernet->prefix} already exists..");
        } else {
            //create SUPERNET
            $supernet = Prefixes::getNextAvailable();
            if(isset($supernet->id))
            {
                $this->addLog(1, "Found next available SUPERNET {$supernet->prefix}.");
                $params = [
                    'scope_type'    =>  'dcim.site',
                    'scope_id'      =>  $netboxsite->id,
                    //'site'       =>  $netboxsite->id,
                    'status'        =>  'container',
                    'role'          =>  6,
                    'vrf'           =>  2,
                ];
                try{
                    $siteprovsupernet = $supernet->update($params);
                } catch (\Exception $e) {

                }
                if($siteprovsupernet->id)
                {
                    $this->addLog(1, "Assigned PREFIX {$siteprovsupernet->prefix} to site.");
                } else {
                    $totalstatus = 0;
                    $this->addLog(0, "Failed to assign PREFIX {$supernet->prefix} to site.");
                    $return['status'] = $totalstatus;
                    $return['log'] = $this->logs;
                    return $return;
                }
            }
        }

        //Subnets
        foreach($netboxsite->vlanToRoleMapping() as $vlan => $roleid)
        {
            $network = $netboxsite->generateSiteNetworks($vlan); 
            $prefix = Prefixes::where('prefix', $network['network'] . "/" . $network['bitmask'])->first();
            if(isset($prefix->prefix))
            {
                $this->addLog(0, "PREFIX {$prefix->prefix} for vlan {$vlan} already exists."); 
                  
                if(isset($prefix->scope->id))
                {
                    if($prefix->scope->id != $netboxsite->id)
                    {
                        $this->addLog(0, "PREFIX {$prefix->prefix} for vlan {$vlan} is not assigned to netbox site ID {$netboxsite->id}.");   
                    }
                }
            } else {
                $prefix = $netboxsite->deployActivePrefix($vlan);
                if(isset($prefix->id))
                {
                    $this->addLog(1, "Created PREFIX {$prefix->prefix} for vlan {$vlan}.");
                } else {
                    $this->addLog(0, "Failed to create PREFIX for vlan {$vlan}.");
                }
            }
            if(isset($prefix->id))
            {
                $range = $prefix->getDhcpIpRange();
                if(isset($range->id))
                {
                    $this->addLog(0, "RANGE ID {$range->id} {$range->display} for vlan {$vlan} already exists.");
                } else {
                    $range = $netboxsite->deployIpRange($vlan);
                    if(isset($range->id))
                    {
                        $this->addLog(1, "Created IP RANGE ID {$range->id} : {$range->display} for vlan {$vlan}.");
                    } else {
                        $this->addLog(0, "Failed to create IP RANGE for vlan {$vlan}.");
                    }                    
                }
            }

        }

        $location = $netboxsite->getDefaultLocation();
        if(isset($location->id))
        {
            $this->addLog(1, "Default Location ID {$location->id} already exists.");
        } else {
            $params = [
                'name'  =>  'MAIN_MDF',
                'slug'  =>  'main_mdf',
                'site'  =>  $netboxsite->id,
            ];
            $location = Locations::create($params);
    
            if(isset($location->id))
            {
                $this->addLog(1, "Created Location ID {$location->id} and added to site.");
            } else {
                $totalstatus = 0;
                $this->addLog(0, "Failed to create Location.");
                $return['status'] = $totalstatus;
                $return['log'] = $this->logs;
                return $return;
            }
        }

        //return fresh copy of Netbox Site
        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = Sites::find($netboxsite->id);
        return $return;
    }

    public function getDhcpScopes($sitecode)
    {
        $site = Sites::where('name__ic', $sitecode)->first();
        if(!isset($site->id))
        {
            return null;
        }
        return $site->getDhcpScopes();
    }

    public function deployDhcpScope($sitecode, $vlan)
    {
        $site = Sites::where('name__ic', $sitecode)->first();
        if(!isset($site->id))
        {
            $this->addLog(0, "Unable to find site with name {$sitecode}.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        }

        if(!isset($vlan) || !isset($site->vlanToRoleMapping()[$vlan]))
        {
            $this->addLog(0, "Vlan {$vlan} is not valid for site {$sitecode}.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        }

        $roleid = $site->vlanToRoleMapping()[$vlan];
        //$prefix = Prefixes::where('site_id',$site->id)->where('status','active')->where('role_id',$roleid)->first();
        $prefix = Prefixes::where('scope_type',"dcim.site")->where('scope_id',$site->id)->where('status','active')->where('role_id',$roleid)->first();
        if(!isset($prefix->id))
        {
            $this->addLog(0, "Unable to find PREFIX with role_id {$roleid} for {$sitecode}.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        }

        $totalstatus = 1;

        $exists = $prefix->getDhcpScope();
        if(isset($exists->scopeID))
        {
            $this->addLog(0, "Scope {$prefix->cidr()['network']} already exists for {$sitecode}.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        }

        $scope = null;
        try{
            $start = microtime(true);
            $scope = $prefix->deployDhcpScope();
            $end = microtime(true);
        } catch (\Exception $e) {
            
        }

        if(isset($scope->scopeID))
        {
            $this->addLog(1, "Deployed DHCP scope {$scope->scopeID} for site {$sitecode} in " . round($end - $start,1) . " seconds.");
        } else {
            $totalstatus = 0;
            $this->addLog(0, "Failed to deploy DHCP scope {$prefix->cidr()['network']} for vlan {$vlan} for site {$sitecode}.");
        }

        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = $scope;
        return $return;
    }

    public function getMistSite($sitecode)
    {

    }

    public function deployMistSite(Request $request, $sitecode)
    {
        $netboxsite = Sites::where('name__ie', $sitecode)->first();
        if(isset($netboxsite->id))
        {
            $this->addLog(1, "Netbox SITE ID {$netboxsite->id} found.");
        } else {
            $this->addLog(0, "Netbox SITE {$sitecode} NOT found.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        }
        $mistsite = Site::findByName($sitecode);
        if(!isset($mistsite->id))
        {
            $this->addLog(1, "Mist SITE {$sitecode} does not yet exist.");
        } else {
            $this->addLog(0, "Mist SITE ID {$mistsite->id} already exists.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = $mistsite;
            return $return;
        }
        //Stupid code to check for duplicate SITES with same name in SNOW, cuz that exists for some reason.
        $snowlocs = Location::where('companyISNOTEMPTY')->where('name',$sitecode)->get();
        if($snowlocs->count() > 1)
        {
            $this->addLog(0, "Multiple SNOW Locations for site {$sitecode}, Please fix.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = $snowlocs;
            return $return;
        }
        try{
            $mistsite = $netboxsite->createMistSite();
        } catch (\Exception $e) {
            
        }
        if(isset($mistsite->id))
        {
            $this->addLog(1, "Mist SITE ID {$mistsite->id} has been created.");
            $return['status'] = 1;
            $return['data'] = $mistsite;
        } else {
            $this->addLog(0, "Mist SITE {$sitecode} failed to create.");
            $return['status'] = 0;
            $return['data'] = null;
        }
        $return['log'] = $this->logs;
        return $return;
    }

    public function getNetboxDevices($sitecode)
    {

    }

    public function deployNetboxDevices(Request $request, $sitecode)
    {
        $totalstatus = 1;
        $newdevices = [];
        $site = Sites::where('name__ic',$sitecode)->first();
        if(!isset($site->id))
        {
            $this->addLog(0, "SITE not found.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "SITE ID {$site->id} found.");
        }

        $devices = $request->collect();
        foreach($devices as $device)
        {
            unset($nameexists);
            unset($serialexists);
            unset($modelexists);
            unset($roleid);
            unset($newdevice);
            if(!isset($device['name']))
            {
                $this->addLog(0, "Device NAME is missing, skipping.");
                $totalstatus = 0;
                continue;
            }
            if(!isset($device['serial']))
            {
                $this->addLog(0, "Device SERIAL is missing, skipping.");
                $totalstatus = 0;
                continue;
            }
            if(!isset($device['model']))
            {
                $this->addLog(0, "Device MODEL is missing, skipping.");
                $totalstatus = 0;
                continue;
            }

            $nameexists = Devices::where('name__ie',$device['name'])->first();
            if(isset($nameexists->id))
            {
                $this->addLog(0, "Device with name {$nameexists->name} already exists, skipping.");
                $totalstatus = 0;
                continue;
            }
            $serialexists = Devices::where('serial__ie',$device['serial'])->first();
            if(isset($serialexists->id))
            {
                $this->addLog(0, "Device with serial {$serialexists->serial} already exists, skipping.");
                $totalstatus = 0;
                continue;
            }
            $modelexists = DeviceTypes::where('model__ie', $device['model'])->first();
            if(!isset($modelexists->id))
            {
                $this->addLog(0, "DEVICE-TYPE {$device['model']} does not exist, skipping.");
                $totalstatus = 0;
                continue;
            }

            $rolecode = substr(strtolower($device['name']), 8, 3);
            foreach(Devices::getRoleMapping() as $key => $value)
			{
				if($rolecode == $key)
				{
					$roleid = $value;
					break;
				}
			}

            if(!isset($roleid))
            {
                $this->addLog(0, "Unable to determine ROLE for device type {$rolecode}, skipping.");
                $totalstatus = 0;
                continue;
            }

            $location = $site->getDefaultLocation();
            if(!isset($location->id))
            {
                $this->addLog(0, "Default LOCATION not found for site {$site->name}, skipping.");
                $totalstatus = 0;
                continue;
            }
            
            $params = [
                'name'			=>	strtoupper($device['name']),
                'device_type'	=>	$modelexists->id,
                'role'			=>	$roleid,
                'site'			=>	$site->id,
                'location'		=>	$location->id,
                'serial'        =>  strtoupper($device['serial']),
            ];

            $newdevice = Devices::create($params);
            if(isset($newdevice->id))
            {
                $this->addLog(1, "Successfully added DEVICE {$newdevice->name}.");
            }
            $newdevices[] = $newdevice;
        }
        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = $newdevices;
        return $return;
    }

    public function deployMistDevices($sitecode)
    {
        $totalstatus = 1;
        $netboxsite = Sites::where('name__ic',$sitecode)->first();
        if(!isset($netboxsite->id))
        {
            $this->addLog(0, "SITE {$sitecode} not found.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "SITE ID {$netboxsite->id} found.");
        }

        $mistsite = $netboxsite->getMistSite();
        if(!isset($mistsite->id))
        {
            $this->addLog(0, "Unable to find MIST SITE {$sitecode}.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "Found MIST SITE ID: {$mistsite->id}.");
        }

        $devices = $netboxsite->devices();
        if($devices->count() == 0)
        {
            $this->addLog(0, "NETBOX SITE ID {$netboxsite->id}: No devices found for site.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        }

        foreach($devices as $device)
        {
            unset($mistdevice);
            if(!isset($device->device_type->manufacturer->name))
            {
                $this->addLog(0, "NETBOX DEVICE ID {$device->id}: Unable to determine Manufacturer for device.");
                $totalstatus = 0;
                continue;
            }
            if($device->device_type->manufacturer->name != "Juniper")
            {
                $this->addLog(0, "NETBOX DEVICE ID {$device->id}: Device is not a Juniper device.");
                $totalstatus = 0;
                continue;
            }
            if(!isset($device->serial))
            {
                $this->addLog(0, "NETBOX DEVICE ID {$device->id}: Device does not have a valid serial number.");
                $totalstatus = 0;
                continue;
            }
            $mistdevice = Device::findBySerial($device->serial);
            if(!isset($mistdevice->serial))
            {
                $this->addLog(0, "NETBOX DEVICE ID {$device->id}: Unable to find matching MIST DEVICE with serial {$device->serial}.");
                $totalstatus = 0;
                continue;
            }
            $this->addLog(1, "NETBOX DEVICE ID {$device->id}: found matching MIST DEVICE with serial {$device->serial}.");
            if($mistdevice->site_id)
            {
                $this->addLog(0, "NETBOX DEVICE ID {$device->id}: Device is already assigned to a site in Mist.");
                $totalstatus = 0;
                continue;
            }
            $status = $mistdevice->assignToSite($mistsite->id);
            
        }
    }

    public function getNetboxDeviceTypesSummarized()
    {
        $types = DeviceTypes::all();
        foreach($types as $type)
        {
            $return[] = $type->model;
        }
        sort($return);
        return $return;
    }

}
