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
use App\Models\Gizmo\Dhcp;

class ProvisioningController extends Controller
{

    public function __construct()
    {
	    $this->middleware('auth:api');
    }

    public function getSnowLocations()
    {
        $locs = Location::where('companyISNOTEMPTY')->where('u_network_mob_dateISEMPTY')->where('u_network_demob_dateISEMPTY')->get();
        if(!$locs)
        {
            $return['status'] = 0;
            $return['msg'] = "SNOW LOCATIONS were unable to be retreived.";
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
        $return['status'] = 1;
        $return['msg'] = "SNOW LOCATIONS successfully retreived.";
        //$return['count'] = count($sitecodes);
        $return['data'] = $sitecodes;
        return json_encode($return);
    }

    public function getSnowLocation($sitecode)
    {
        $loc = Location::where('companyISNOTEMPTY')->where('name', $sitecode)->get();
        if($loc)
        {
            $return['status'] = 1;
            $return['msg'][] = "SNOW LOCATION successfully retreived.";
            $return['data'] = $loc;
            return json_encode($return);
        } else {
            $return['status'] = 0;
            $return['msg'][] = "SNOW LOCATION was not found.";
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
        //Attempt to get existing snow location.
        $snowloc = Location::where('name', $sitecode)->first();
        if(!$snowloc)
        {
            $return['status'] = 0;
            $return['msg'][] = "Unable to find valid SNOW location."; 
            return json_encode($return);
        } else {
            $return['msg'][] = "Found SNOW location ID {$snowloc->sys_id}."; 
        }

        //Attempt to get existing netbox site.
        $netboxsite = Sites::where('name__ie', $sitecode)->first();
        if(isset($netboxsite->id))
        {
            $return['msg'][] = "Netbox SITE ID {$netboxsite->id} already exists.";
        } else {
            $netboxsite = $snowloc->createNetboxSite();
            if(isset($netboxsite->id))
            {
                $return['msg'][] = "Created Netbox SITE ID {$netboxsite->id}.";
            } else {
                $return['msg'][] = "Failed to create new Netbox SITE.";
                $return['status'] = 0;
                return json_encode($return);
            }
        }

        //Attempt to get existing ASN, if none create new.
        $asn = $netboxsite->getAsns();
        if($asn)
        {
            $return['msg'][] = "ASN already exists..";
        } else {
            //create ASN
            $newasn = AsnRanges::where('name','AUTO-PROVISIONING')->first()->getNextAvailableAsn();
            if(isset($newasn->asn))
            {
                $return['msg'][] = "Available ASN {$newasn->asn} has been found.";
                $asn = Asns::where('asn',$newasn->asn)->first();
                if(!isset($asn->id))
                {
                    $params = [
                        'asn'   =>  $newasn->asn,
                        'rir'   =>  1,
                    ];
                    $asn = Asns::create($params);
                    if(isset($asn->id))
                    {
                        $return['msg'][] = "Created new ASN ID {$asn->id}.";

                        $params = [
                            'asns'  =>  [$asn->id],
                        ];
                        $netboxsite = $netboxsite->update($params);
                        if(isset($netboxsite->id))
                        {
                            $return['msg'][] = "Assigned ASN ID {$asn->id} to site.";
                        } else {
                            $return['msg'][] = "Failed to assign ASN ID {$asn->id} to site.";
                            $return['status'] = 0;
                            return $return;                            
                        }
                    } else {
                        $return['msg'][] = "Failed to find a new ASN.";
                        $return['status'] = 0;
                        return $return;
                    }
                }
            } else {
                $return['msg'][] = "Failed to find a new ASN.";
                $return['status'] = 0;
                return $return;
            }
        }

        $supernets = $netboxsite->getSupernets();
        if($supernets->isNotEmpty())
        {
            $return['msg'][] = "SUPERNET {$supernets->first()->prefix} already exists..";
        } else {
            //create SUPERNET
            $supernet = Prefixes::getNextAvailable();
            if(isset($supernet->id))
            {
                $return['msg'][] = "Found next available SUPERNET {$supernet->prefix}.";
                $params = [
                    'site'      =>  $netboxsite->id,
                    'status'    =>  'container',
                    'role'      =>  6,
                ];
                $updatedsn = $supernet->update($params);
                if($updatedsn->id)
                {
                    $return['msg'][] = "Assigned PREFIX {$updatedsn->prefix} to site.";
                } else {
                    $return['msg'][] = "Failed to assign PREFIX {$supernet->prefix} to site.";
                    $return['status'] = 0;
                    return $return;
                }
            }
        }

        $location = Locations::where('site_id',$netboxsite->id)->where('name','MAIN_MDF')->first();
        if(isset($location->id))
        {
            $return['msg'][] = "Location ID {$location->id} already exists.";
        } else {
            $params = [
                'name'  =>  'MAIN_MDF',
                'slug'  =>  'main_mdf',
                'site'  =>  $netboxsite->id,
            ];
            $location = Locations::create($params);
    
            if(isset($location->id))
            {
                $return['msg'][] = "Created Location ID {$location->id} and added to site.";
            } else {
                $return['msg'][] = "Failed to create Location.";
                $return['status'] = 0;
                return $return;
            }
        }

        //return fresh copy of Netbox Site
        $return['data'] = Sites::find($netboxsite->id);
        $return['status'] = 1;
        return $return;
    }

    public function getDhcpScopes($sitecode)
    {
        $scope = Dhcp::getScopesBySitecode($sitecode);
        return json_encode($scope);
    }

    public function deployDhcpScopes(Request $request, $sitecode)
    {

    }

    public function getMistSite($sitecode)
    {

    }

    public function deployMistSite(Request $request, $sitecode)
    {

    }

    public function getNetboxDevices($sitecode)
    {

    }

    public function deployNetboxDevices(Request $request, $sitecode)
    {

    }

}
