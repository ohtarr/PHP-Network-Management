<?php

namespace App\Http\Controllers\Validation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ServiceNow\Location;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\IPAM\Asns;

class ValidationController extends Controller
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
        if(!isset($snowloc->sys_id))
        {
            $this->addLog(0, "Unable to find valid SNOW location.");
            $totalstatus = 0;
        } else {
            $this->addLog(1, "Found SNOW location ID {$snowloc->sys_id}.");
        }

        //Validate that the Netbox site exists
        $netboxsite = Sites::where('name__ie', $sitecode)->first();
        if(isset($netboxsite->id))
        {
            $this->addLog(1, "Netbox SITE ID {$netboxsite->id} exists.");
        } else {
            $this->addLog(0, "Netbox SITE does not exist.");
            $totalstatus = 0;
        }

        //Validate that the Netbox site info matches snow info
        if(isset($netboxsite->id))
        {
            if(isset($snowloc->sys_id))
            {
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
                    $this->addLog(1, "Netbox SITE ID {$netboxsite->id} matches SNOW location ID {$snowloc->id}");
                } else {
                    $this->addLog(0, "Netbox SITE ID {$netboxsite->id} does NOT match SNOW location ID {$snowloc->id}");
                    $totalstatus = 0;
                }
            }

            $prefix = $netboxsite->getProvisioningSupernet();
            if(isset($prefix->id))
            {
                $this->addLog(1, "Netbox PREFIX {$prefix->id} is assigned to site ID {$netboxsite->id}");
            } else {
                $this->addLog(0, "Netbox PREFIX not found for site ID {$netboxsite->id}");
                $totalstatus = 0;
            }

            if(isset($prefix->status))
            {
                if($prefix->status->value == "container")
                {
                    $this->addLog(1, "Netbox PREFIX {$prefix->id} is set to status CONTAINER");
                }
            } else {
                $this->addLog(0, "Netbox PREFIX is NOT set to status CONTAINER");
                $totalstatus = 0;
            }

            $asn = Asns::where('site_id',$netboxsite->id)->first();
            if(isset($asn->id))
            {
                $this->addLog(1, "Netbox ASN {$asn->id} is assigned to site ID {$netboxsite->id}");
            } else {
                $this->addLog(0, "Netbox ASN not found for site ID {$netboxsite->id}");
                $totalstatus = 0;
            }

            $location = $netboxsite->locations()->first();
            if(isset($location->id))
            {
                $this->addLog(1, "Netbox LOCATION id {$location->id} is assigned to site ID {$netboxsite->id}");
            } else {
                $this->addLog(0, "Netbox LOCATION not found for site ID {$netboxsite->id}");
                $totalstatus = 0;
            }
        } else {

            $this->addLog(0, "Netbox SITE does NOT match SNOW location ID {$snowloc->id}");
            $this->addLog(0, "Netbox PREFIX not found for site");
            $this->addLog(0, "Netbox PREFIX is NOT set to status CONTAINER");
            $this->addLog(0, "Netbox ASN not found for site.");
            $this->addLog(0, "Netbox LOCATION not found for site");
        }

        return [
            'status'  => $totalstatus,
            'log'     => $this->logs,
            'data'    => $netboxsite,
        ];

        //PREFIX EXISTS
            //ONLY ONE PREFIX ASSIGNED TO SITE
            //PREFIX SET TO CONTAINER
        //ASN EXISTS
            //ONLY ONE ASN ASSIGNED TO SITE
        //DEFAULT LOCATION EXISTS

    }

}
