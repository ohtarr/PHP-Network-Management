<?php

namespace App\Http\Controllers\Validation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ServiceNow\Location;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\IPAM\Asns;
use App\Models\Netbox\IPAM\Prefixes;

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

            $provprefix = $netboxsite->getProvisioningSupernet();
            if(isset($provprefix->id))
            {
                $this->addLog(1, "Netbox PREFIX {$provprefix->prefix} (ID: {$provprefix->id}) is assigned to site ID {$netboxsite->id}");
            } else {
                $this->addLog(0, "Netbox PREFIX not found for site ID {$netboxsite->id}");
                $totalstatus = 0;
            }

            if(isset($provprefix->status))
            {
                if($provprefix->status->value == "container")
                {
                    $this->addLog(1, "Netbox PREFIX {$provprefix->prefix} is set to status CONTAINER");
                }
            } else {
                $this->addLog(0, "Netbox PREFIX {$provprefix->prefix} is NOT set to status CONTAINER");
                $totalstatus = 0;
            }

            if(isset($provprefix->role->name))
            {
                if($provprefix->role->name == "SITE_SUPERNET")
                {
                    $this->addLog(1, "Netbox PREFIX {$provprefix->prefix} is set to role SITE_SUPERNET");
                }
            } else {
                $this->addLog(0, "Netbox PREFIX {$provprefix->prefix} is NOT set to role SITE_SUPERNET");
                $totalstatus = 0;
            }

            $networks = $netboxsite->generateSiteNetworks();
            foreach($networks as $vlan => $network)
            {
                unset($prefixstring);
                unset($scope);
                $prefixstring = $network['network'] . '/' . $network['bitmask'];
                $prefix = Prefixes::where('prefix', $prefixstring)->first();
                if(isset($prefix->id))
                {
                    $this->addLog(1, "Netbox PREFIX {$prefixstring} exists");
                } else {
                    $this->addLog(0, "Netbox PREFIX {$prefixstring} does NOT exist");
                    $totalstatus = 0;
                    continue;
                }

                if($prefix->role->id == $network['role'])
                {
                    $this->addLog(1, "Netbox PREFIX {$prefixstring} is set to correct ROLE ({$prefix->role->name})");
                } else {
                    $this->addLog(0, "Netbox PREFIX {$prefixstring} is NOT set to correct ROLE.");
                    $totalstatus = 0;
                    continue;
                }

                $iprange = $prefix->getIpRanges()->where('role.name','DHCP_SCOPE')->first();
                if(isset($iprange->id))
                {
                    $this->addLog(1, "Netbox IPRANGE {$iprange->display} exists for prefix {$prefix->prefix}");
                } else {
                    $this->addLog(0, "Netbox IPRANGE does NOT exist for prefix {$prefix->prefix}");
                    $totalstatus = 0;
                    continue;
                }

                $scope = $iprange->getDhcpScope();
                if(isset($scope->scopeID))
                {
                    $this->addLog(1, "DHCP Scope {$scope->scopeID} exists for prefix {$prefix->prefix}");
                    if(isset($network['gateway']))
                    {
                        $option = $scope->findOption(3);
                        if(!isset($option['value'][0]))
                        {
                            $this->addLog(0, "DHCP Scope {$scope->scopeID} does NOT have a gateway!}");                            
                        } else {
                            if($option['value'][0] == $network['gateway'])
                            {
                                $this->addLog(1, "DHCP Scope {$scope->scopeID} has correct gateway {$option['value'][0]}");
                            } else {
                                $this->addLog(0, "DHCP Scope {$scope->scopeID} has incorrect gateway {$option['value'][0]}");
                            }
                        }
                    }
                    $option = $scope->findOption(6);
                    for($x = 1; $x <= 3; $x++)
                    {
                        $arraykey = $x - 1;
                        if(isset($network['dns' . $x]))
                        {
                            if(isset($option['value'][$arraykey]))
                            {
                                if($option['value'][$arraykey] == $network['dns' . $x])
                                {
                                    $this->addLog(1, "DHCP Scope {$scope->scopeID} has correct dns{$x} OPTION ({$option['value'][$arraykey]})");
                                } else {
                                    $this->addLog(0, "DHCP Scope {$scope->scopeID} has incorrect dns{$x} OPTION ({$option['value'][$arraykey]})");
                                }
                            } else {
                                $this->addLog(0, "DHCP Scope {$scope->scopeID} is missing dns{$x} OPTION");
                            }
                        }
                    }
                    $option = $scope->findOption(150);
                    for($x = 1; $x <= 2; $x++)
                    {
                        $arraykey = $x - 1;
                        if(isset($network['cm' . $x]))
                        {
                            if(isset($option['value'][$arraykey]))
                            {
                                if($option['value'][$arraykey] == $network['cm' . $x])
                                {
                                    $this->addLog(1, "DHCP Scope {$scope->scopeID} has correct cm{$x} OPTION ({$option['value'][$arraykey]})");
                                } else {
                                    $this->addLog(0, "DHCP Scope {$scope->scopeID} has incorrect cm{$x} OPTION ({$option['value'][$arraykey]})");
                                }
                            } else {
                                $this->addLog(0, "DHCP Scope {$scope->scopeID} is missing cm{$x} OPTION");
                            }
                        }
                    }
                } else {
                    $this->addLog(0, "DHCP Scope does NOT exist for prefix {$prefix->prefix}");
                    $totalstatus = 0;
                }

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
