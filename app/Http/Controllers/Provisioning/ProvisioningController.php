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
use App\Models\Netbox\DCIM\VirtualChassis;
use App\Models\Netbox\DCIM\Manufacturers;
use App\Models\ServiceNow\Location;
use App\Models\Mist\Site;
use App\Models\Mist\Device;
use App\Models\Mist\SiteGroup;
use App\Models\Mist\GatewayTemplate;
use App\Models\Mist\NetworkTemplate;
use App\Models\Mist\RfTemplate;
use App\Models\Gizmo\Dhcp;
use Illuminate\Support\Facades\Log;

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
        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . debug_backtrace()[1]['function'] . ": " . $msg);
    }

    public function getSnowLocations()
    {
        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__);
        $totalstatus = 1;
        $locs = Location::where('companyISNOTEMPTY')->where('u_network_demob_dateISEMPTY')->get();
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
        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__);
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
        $user = auth()->user();
		if ($user->cant('provision-netbox-sites')) {
			abort(401, 'You are not authorized');
        }
        $logcontext = [
            'sitecode'  => $sitecode,
        ];
        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__, $logcontext);
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
        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__);
        $site = Sites::where('name__ic', $sitecode)->first();
        if(!isset($site->id))
        {
            return null;
        }
        return $site->getDhcpScopes();
    }

    public function deployDhcpScope($sitecode, $vlan)
    {
        $user = auth()->user();
		if ($user->cant('provision-dhcp-scopes')) {
			abort(401, 'You are not authorized');
        }

        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__);
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
        
        $overlaps = Dhcp::findOverlap($prefix->Network(), $prefix->Length());
        if($overlaps->count() > 0)
        {
            $overlapsmsg = "";
            foreach($overlaps as $overlap)
            {
                $overlapsmsg .= $overlap->scopeID . ',';
            }

            $this->addLog(0, "Scope {$prefix->cidr()['network']} has overlapping scopes! {$overlapsmsg}");
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
        $user = auth()->user();
		if ($user->cant('provision-mist-sites')) {
			abort(401, 'You are not authorized');
        }

        $sitecode = strtoupper($sitecode);
        $submitted = $request->collect();

        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__);
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

		$mistsettings = $netboxsite->generateMistSiteSettings();
		
		if(!$mistsettings)
		{
			$msg = "Unable to generate Mist Site Settings";
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}

		$sitegroupids = $netboxsite->generateMistSiteGroups();

		$sitegroups = SiteGroup::all();

		//check if site groups exist
		foreach($sitegroupids as $sitegroupid)
		{
			$exists = 0;
			foreach($sitegroups as $sitegroup)
			{
				if($sitegroupid == $sitegroup->id)
				{
					$exists = 1;
					break;
				}
			}
			if($exists == 0)
			{
				$msg = 'SITEGROUP does not exist!';
				print $msg . PHP_EOL;
				throw new \Exception($msg);
			}
		}
	
		$mistsiteparams = $netboxsite->generateMistSiteParameters();

        if(isset($submitted['gateway_template']))
        {
            if($submitted['gateway_template'])
            {
                $gatewaytemplate = GatewayTemplate::where('name', $submitted['gateway_template'])->first();
                if(isset($gatewaytemplate->id))
                {
                    $mistsiteparams['gatewaytemplate_id'] = $gatewaytemplate->id;
                }
            }
        }

        if(isset($submitted['network_template']))
        {
            if($submitted['network_template'])
            {
                $networktemplate = NetworkTemplate::where('name', $submitted['network_template'])->first();
                if(isset($networktemplate->id))
                {
                    $mistsiteparams['networktemplate_id'] = $networktemplate->id;
                }
            }
        }

        if(isset($submitted['rf_template']))
        {
            if($submitted['rf_template'])
            {
                $rftemplate = RfTemplate::where('name', $submitted['rf_template'])->first();
                if(isset($rftemplate->id))
                {
                    $mistsiteparams['rftemplate_id'] = $rftemplate->id;
                }
            }
        }

        $mistsite = Site::create($mistsiteparams);
        if(isset($mistsite->id))
        {
            $this->addLog(1, "Mist SITE ID {$mistsite->id} has been created.");
            $return['status'] = 1;
            $return['data'] = $mistsite;
            $mistsite->updateSettings($mistsettings);
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
        $user = auth()->user();
		if ($user->cant('provision-netbox-devices')) {
			abort(401, 'You are not authorized');
        }

        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__);
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

        $location = $site->getDefaultLocation();
        if(!isset($location->id))
        {
            $this->addLog(0, "Default LOCATION not found for site {$site->name}, skipping.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        }

        $models = DeviceTypes::all();

        $devices = $request->collect();
        foreach($devices as $device)
        {
            unset($nameexists);
            unset($serialexists);
            unset($modelexists);
            unset($roleid);
            unset($newdevice);
            unset($basename);
            unset($memberid);
            //unset($virtualchassis);

            if(!isset($device['name']))
            {
                $this->addLog(0, "Device NAME is missing, skipping.");
                $totalstatus = 0;
                continue;
            }
            if(strpos(trim($device['name']), ' ') !== false)
            {
                $this->addLog(0, "Device NAME {$device['name']} contains spaces, not allowed, skipping.");
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

            $nameexists = Devices::where('name__ie',trim($device['name']))->first();
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
            $modelexists = $models->where('model', $device['model'])->first();
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
      
            $reg = "/^(\S+)_(\d)$/";
            if(preg_match($reg, $device['name'], $hits))
            {
                $basename = $hits[1];
                $memberid = $hits[2];
            }
            if(isset($basename))
            {
                if(!isset($virtualchassis->name) || (isset($virtualchassis->name) && $virtualchassis->name != $basename))
                {
                    $virtualchassis = VirtualChassis::where('name', $basename)->first();
                }
                if(!isset($virtualchassis->id))
                {
                    $this->addLog(1, "VirtualChassis {$basename} NOT found, attempting to create.");
                    $virtualchassis = VirtualChassis::create(['name' => $basename]);
                    if(!isset($virtualchassis->id))
                    {
                        $this->addLog(0, "VirtualChassis {$basename} FAILED to create, skipping device.");
                        $totalstatus = 0;
                        continue;
                    } else {
                        $this->addLog(1, "Existing VirtualChassis {$basename} found.");
                    }
                } else {
                    $this->addLog(1, "Existing VirtualChassis {$basename} found.");
                }
            }

            $params = [
                'name'			=>	trim(strtoupper($device['name'])),
                'role'			=>	$roleid,
                'device_type'   =>  $modelexists->id,
                'site'			=>	$site->id,
                'location'		=>	$location->id,
                'serial'        =>  trim(strtoupper($device['serial'])),
            ];
            if(isset($virtualchassis->id))
            {
                $params['virtual_chassis'] = $virtualchassis->id;
                $params['vc_position'] = $memberid;
                if($memberid == 0)
                {
                    $params['vc_priority'] = 200;
                }
            }
            if(isset($device['ip']) && $device['ip'])
            {
                $params['custom_fields']['ip'] = $device['ip'];
            }
            $newdevice = Devices::create($params);

            if(isset($newdevice->id))
            {
                $this->addLog(1, "Successfully added DEVICE {$newdevice->name}.");
            }
            if(isset($memberid) && $memberid != 0)
            {
                $newdevice->renameInterfaces2($memberid);
            }
            if(isset($memberid) && $memberid == 0)
            {
                $params = [
                    'master'    =>  $newdevice->id,
                ];
                $virtualchassis = $virtualchassis->update($params);
                if(isset($virtualchassis->master->id))
                {
                    $this->addLog(1, "Successfully set switch {$newdevice->name} as MASTER on virtual chassis {$virtualchassis->name}.");
                } else {
                    $this->addLog(0, "FAILED to set switch {$newdevice->name} as MASTER on virtual chassis {$virtualchassis->name}.");
                }
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
        $user = auth()->user();
		if ($user->cant('provision-mist-devices')) {
			abort(401, 'You are not authorized');
        }

        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__);
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
        $manufacturer = Manufacturers::where('name','Juniper')->first();
        if(!isset($manufacturer->id))
        {
            $this->addLog(0, "Unable to find Netbox Manufacturer for Juniper.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        }
        $devices = Devices::where('site_id', $netboxsite->id)->where('manufacturer_id',$manufacturer->id)->get();
        if($devices->count() == 0)
        {
            $this->addLog(0, "NETBOX SITE ID {$netboxsite->id}: No devices found for site.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        }
        $deploy = [];
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
            if(!isset($device->serial) && $device->serial)
            {
                $this->addLog(0, "NETBOX DEVICE ID {$device->id}: Device does not have a valid serial number.");
                $totalstatus = 0;
                continue;
            }
            //Find existing Mist Device
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
            //Assign Mist Device to site.
            $assignresults = $mistdevice->assignToSite($mistsite->id);
            //fetch fresh copy of mistdevice
            $assignmatch = 0;
            foreach($assignresults->success as $assignmac)
            {
                if($assignmac == $mistdevice->mac)
                {
                    $assignmatch = 1;
                    break;
                }
            }
            if($assignmatch == 1)
            {
                $this->addLog(1, "Assigned device to MISTSITE {$mistsite->name} successfully.");
                $mistdevice->site_id = $mistsite->id;
            } else {
                $totalstatus = 0;
                $this->addLog(0, "FAILED to assign device {$mistdevice->serial} to MISTSITE {$mistsite->name}.");
                continue;
            }
            //$mistdevice = Device::findBySerial($device->serial); // not do this, confirm via return
            //RENAME Mist Device
            $params = ['name'   =>  $device->name];
            try{
                $mistdevice = $mistdevice->update($params);
            } catch (\Exception $e) {
                $this->addLog(1, "FAILED to rename device with serial {$mistdevice->serial}");
            }
            if(isset($mistdevice->name) && $mistdevice->name)
            {
                $this->addLog(1, "Renamed MIST DEVICE to {$mistdevice->name} successfully.");
            } else {
                $this->addLog(0, "Failed to rename MIST DEVICE with serial {$mistdevice->serial}.");
            }
        }
        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = null;
        return $return;
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

    public function getAvailableProvIps($sitecode, $qty = 50)
    {
        $netboxsite = Sites::where('name__ic',$sitecode)->first();
        if(!isset($netboxsite->id))
        {
            $this->addLog(0, "SITE {$sitecode} not found.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "SITE: {$netboxsite->name} ID:{$netboxsite->id} found.");
        }
        $ips = $netboxsite->getAvailableProvIps($qty);
        if(!$ips)
        {
            $this->addLog(0, "Unable to retrieve any available IPs");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $count = count($ips);
            $this->addLog(1, "Retrieved {$count} IPs from site {$netboxsite->name}");
        }
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $ips;
        return $return;
    }

}
