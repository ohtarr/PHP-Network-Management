<?php

namespace App\Http\Controllers\Validation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ServiceNow\Location;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\IPAM\Asns;

class ValidationController extends Controller
{

    public function __construct()
    {
	    $this->middleware('auth:api');
    }

    public function getSnowToNetboxAddressMap()
    {
        return [
            'u_street_number'			=>	'STREET_NUMBER',
            'u_street_predirectional'	=>	'STREET_PREDIRECTIONAL',
            'u_street_name'				=>	'STREET_NAME',
            'u_street_suffix'			=>	'STREET_SUFFIX',
            'u_street_postdirectional'	=>	'STREET_POSTDIRECTIONAL',
            'u_secondary_unit_indicator'=>	'STREET2_SECONDARYUNITINDICATOR',
            'u_secondary_number'		=>	'STREET2_SECONDARYNUMBER',
            'city'						=>	'CITY',
            'state'						=>	'STATE',
            'zip'						=>	'POSTAL_CODE',
            'country'					=>	'COUNTRY',
        ];
    }

    public function validateNetboxSite(Request $request, $sitecode)
    {
        //Validate that the snow location exists
        $totalstatus = 1;
        $snowloc = Location::where('companyISNOTEMPTY')->where('name', $sitecode)->first();
        $log = [];
        if(!isset($snowloc->sys_id))
        {
            $log['status'] = 0;
            $log['msg'] = "Unable to find valid SNOW location."; 
            $totalstatus = 0;
        } else {
            $log['status'] = 1;
            $log['msg'] = "Found SNOW location ID {$snowloc->sys_id}."; 
        }
        $logs[] = $log;

        //Validate that the Netbox site exists
        $log = [];
        $netboxsite = Sites::where('name__ie', $sitecode)->first();
        if(isset($netboxsite->id))
        {
            $log['status'] = 1;
            $log['msg'][] = "Netbox SITE ID {$netboxsite->id} exists.";
        } else {
            $log['status'] = 0;
            $log['msg'][] = "Netbox SITE does not exist.";
            $totalstatus = 0;
        }
        $logs[] = $log;

        //Validate that the Netbox site info matches snow info
        $log = [];
        $matches = 1;
        foreach($this->getSnowToNetboxAddressMap() as $snowkey => $netboxkey)
        {
            //$debuglog[] = $snowloc->$snowkey . "=?=" . $netboxsite->custom_fields->$netboxkey;
            if($snowloc->$snowkey != $netboxsite->custom_fields->$netboxkey)
            {
                $matches = 0;
                break;
            }
        }
        if($matches == 1)
        {
            $log['status'] = 1;
            $log['msg'][] = "Netbox SITE ID {$netboxsite->id} matches SNOW location ID {$snowloc->id}";
        } else {
            $log['status'] = 0;
            $log['msg'][] = "Netbox SITE ID {$netboxsite->id} does NOT match SNOW location ID {$snowloc->id}";
            $totalstatus = 0;
        }
        $logs[] = $log;

        $log = [];
        $prefix = $netboxsite->getProvisioningSupernet();
        if(isset($prefix->id))
        {
            $log['status'] = 1;
            $log['msg'][] = "Netbox PREFIX {$prefix->id} is assigned to site ID {$netboxsite->id}";
        } else {
            $log['status'] = 0;
            $log['msg'][] = "Netbox PREFIX not found for site ID {$netboxsite->id}";
            $totalstatus = 0;
        }
        $logs[] = $log;

        $log = [];
        if(isset($prefix->status))
        {
            if($prefix->status->value == "container")
            {
                $log['status'] = 1;
                $log['msg'][] = "Netbox PREFIX {$prefix->id} is set to status CONTAINER";
            }
        } else {
            $log['status'] = 0;
            $log['msg'][] = "Netbox PREFIX is NOT set to status CONTAINER";
            $totalstatus = 0;
        }
        $logs[] = $log;

        $log = [];
        $asn = Asns::where('site_id',$netboxsite->id)->first();
        if(isset($asn->id))
        {
            $log['status'] = 1;
            $log['msg'][] = "Netbox ASN {$asn->id} is assigned to site ID {$netboxsite->id}";
        } else {
            $log['status'] = 0;
            $log['msg'][] = "Netbox ASN not found for site ID {$netboxsite->id}";
            $totalstatus = 0;
        }
        $logs[] = $log;

        $log = [];
        $location = $netboxsite->locations()->first();
        if(isset($location->id))
        {
            $log['status'] = 1;
            $log['msg'][] = "Netbox LOCATION id {$location->id} is assigned to site ID {$netboxsite->id}";
        } else {
            $log['status'] = 0;
            $log['msg'][] = "Netbox LOCATION not found for site ID {$netboxsite->id}";
            $totalstatus = 0;
        }
        $logs[] = $log;


        //PREFIX EXISTS
            //ONLY ONE PREFIX ASSIGNED TO SITE
            //PREFIX SET TO CONTAINER
        //ASN EXISTS
            //ONLY ONE ASN ASSIGNED TO SITE
        //DEFAULT LOCATION EXISTS
        $return['status'] = $totalstatus;
        $return['log'] = $logs;
        //$return['debuglog'] = $debuglog;
        return $return;
    }

}
