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
                    'site'      =>  $netboxsite->id,
                    'status'    =>  'container',
                    'role'      =>  6,
                    'vrf'       =>  2,
                ];
                $siteprovsupernet = $supernet->update($params);
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
            unset($prefix);
            $prefix = $netboxsite->deployActivePrefix($vlan);
            if(isset($prefix->id))
            {
                $this->addLog(1, "Created PREFIX {$prefix->prefix} for vlan {$vlan}.");
            } else {
                $this->addLog(0, "Failed to create PREFIX for vlan {$vlan}.");
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
        $prefix = Prefixes::where('site_id',$site->id)->where('status','active')->where('role_id',$roleid)->first();
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
            $this->addLog(0, "Failed to deploy DHCP scope for vlan {$vlan} for site {$sitecode}.");
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

        $mistsite = $netboxsite->createMistSite();
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

    }

}
