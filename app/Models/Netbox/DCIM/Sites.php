<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\DCIM\Locations;
use App\Models\ServiceNow\Location;
use IPv4\SubnetCalculator;
use App\Models\Gizmo\Dhcp;

#[\AllowDynamicProperties]
class Sites extends BaseModel
{
    protected $app = "dcim";
    protected $model = "sites";

    public function formatAddress()
    {
        $address = [
            'street_number' =>  "",
            'street_predirectional' =>  "",
            'street_name' =>  "",
            'street_suffix' =>  "",
            'street_postdirectional' =>  "",
            'street2_secondaryunitindicator' =>  "",
            'street2_secondarynumber' =>  "",
            'city' =>  "",
            'state' =>  "",
            'postal_code' =>  "",
            'country' =>  "",
            'full'  =>  "",

        ];
        $line1 = "";
        $line2 = "";
        $line3 = "";
        if(isset($this->custom_fields->STREET_NUMBER))
        {
            $address['street_number'] = $this->custom_fields->STREET_NUMBER;
            $line1 .= $this->custom_fields->STREET_NUMBER . " ";
        }
        if(isset($this->custom_fields->STREET_PREDIRECTIONAL))
        {
            $address['street_predirectional'] = $this->custom_fields->STREET_PREDIRECTIONAL;
            $line1 .= $this->custom_fields->STREET_PREDIRECTIONAL . " ";
        }
        if(isset($this->custom_fields->STREET_NAME))
        {
            $address['street_name'] = $this->custom_fields->STREET_NAME;    
            $line1 .= $this->custom_fields->STREET_NAME . " ";
        }
        if(isset($this->custom_fields->STREET_SUFFIX))
        {
            $address['street_suffix'] = $this->custom_fields->STREET_SUFFIX; 
            $line1 .= $this->custom_fields->STREET_SUFFIX;
        }
        if(isset($this->custom_fields->STREET_POSTDIRECTIONAL))
        {
            $address['street_postdirectional'] = $this->custom_fields->STREET_POSTDIRECTIONAL;
            $line1 .= " " . $this->custom_fields->STREET_POSTDIRECTIONAL;
        }
        if(isset($this->custom_fields->STREET_SECONDARYUNITINDICATOR))
        {
            $address['street2_secondaryunitindicator'] = $this->custom_fields->STREET_SECONDARYUNITINDICATOR;
            $line2 .= $this->custom_fields->STREET_SECONDARYUNITINDICATOR . " ";
        }
        if(isset($this->custom_fields->STREET_SECONDARYNUMBER))
        {
            $address['street2_secondarynumber'] = $this->custom_fields->STREET_SECONDARYNUMBER;
            $line2 .= $this->custom_fields->STREET_SECONDARYNUMBER;
        }
        if(isset($this->custom_fields->CITY))
        {
            $address['city'] = $this->custom_fields->CITY;
            $line3 .= $this->custom_fields->CITY . ", ";
        }
        if(isset($this->custom_fields->STATE))
        {
            $address['state'] = $this->custom_fields->STATE;
            $line3 .= $this->custom_fields->STATE . " ";
        }
        if(isset($this->custom_fields->POSTAL_CODE))
        {
            $address['postal_code'] = $this->custom_fields->POSTAL_CODE;
            $line3 .= $this->custom_fields->POSTAL_CODE;
        }
        if(isset($this->custom_fields->COUNTRY))
        {
            $address['country'] = $this->custom_fields->COUNTRY;
        }
        $full = "";
        if($line1)
        {
            $full .= $line1 . PHP_EOL;
        }
        if($line2)
        {
            $full .= $line2 . PHP_EOL;
        }
        if($line3)
        {
            $full .= $line3;
        }
        $address['full'] = $full;
        return $address;
    }

    public function addressExists()
    {
        if(
            $this->custom_fields->STREET_NAME &&
            $this->custom_fields->CITY &&
            $this->custom_fields->STATE &&
            $this->custom_fields->POSTAL_CODE &&
            $this->custom_fields->COUNTRY
        )
        {
            return true;
        }
    }

    public function address()
    {
        if($this->addressExists())
        {
            return $this->formatAddress();
        }
    }

    public function coordinates()
    {
        if($this->latitude && $this->longitude)
        {
            return [
                'latitude'  =>  $this->latitude,
                'longitude' =>  $this->longitude,
            ];
        }
    }

    public function polling()
    {
        if($this->custom_fields->POLLING === true)
        {
            return true;
        }
        return false;
    }

    public function alerting()
    {
        if($this->custom_fields->ALERT === true)
        {
            return true;
        }
        return false;
    }

    public function prefixes()
    {
        $prefixes = new Prefixes($this->query);
        return $prefixes->where('site_id', $this->id)->get();
    }

    public function getActivePrefixes()
    {
        return Prefixes::where('site_id', $this->id)->where('status', 'active')->get();
    }

    public function getSupernets()
    {
        $prefixes = new Prefixes($this->query);
        return $prefixes->where('site_id', $this->id)->where('role_id',6)->get();
    }

    public function getProvisioningSupernet()
    {
        $prefixes = new Prefixes($this->query);
        return $prefixes->where('site_id', $this->id)->where('role_id',6)->where('mask_length',20)->first();
    }

    public function getAsns()
    {
        return collect($this->asns);
    }

    public function getPrimaryAsn()
    {
        return $this->asns[0];
    }

    public function assignNextAvailableAsn()
    {

    }

    public function generateSitePrefix($vlan = null)
    {
        $supernet = $this->getProvisioningSupernet();
        if(!isset($supernet->id))
        {
            return null;
        }

        $IPV4LONG = ip2long($supernet->cidr()['network']);
        $params = [
            1   =>  [
                "network"		=> long2ip($IPV4LONG),
                "netmask"		=> "255.255.252.0",
                "bitmask"       => 22,
                "description"	=> $this->name . " VLAN 1 - WIRED",
                "status"        => "active",
                "role"          => 1,
            ],
            5   =>  [
                "network"		=> long2ip($IPV4LONG + 1024),
                "netmask"		=> "255.255.252.0",
                "bitmask"       => 22,
                "description"	=> $this->name . " VLAN 5 - WIRELESS",
                "status"        => "active",
                "role"          => 2,
            ],
            9   =>  [
                "network"		=> long2ip($IPV4LONG + 2048),
                "netmask"		=> "255.255.252.0",
                "bitmask"       => 22,
                "description"	=> $this->name . " VLAN 9 - VOICE",
                "status"        => "active",
                "role"          => 3,
            ],
            13   =>  [
                "network"		=> long2ip($IPV4LONG + 3072),
                "netmask"		=> "255.255.254.0",
                "bitmask"       => 23,
                "description"	=> $this->name . " VLAN 13 - RESTRICTED",
                "status"        => "active",
                "role"          => 4,
            ],
        ];
        if(!$vlan)
        {
            return $params;
        } elseif($vlan && isset($params[$vlan])){
            return $params[$vlan];
        } else {
            return null;
        }
    }

    public function generateDns()
    {
        $supernet = $this->getProvisioningSupernet();
        if(!isset($supernet->id))
        {
            return null;
        }
        if($supernet->vrf->name == "V101:DATACENTER")
        {
            $dns = [
				'10.252.13.134',
				'10.252.13.133',
				'10.252.13.135'
			];
        } else {
            $dns = [
				'10.251.12.189',
				'10.251.12.190',
			];
        }
        return $dns;        
    }

    public function generateDhcpScopes($vlan = null)
    {
        $supernet = $this->getProvisioningSupernet();
        if(!isset($supernet->id))
        {
            return null;
        }
        $dns = $this->generateDns();

        $IPV4LONG = ip2long($supernet->cidr()['network']);
        $vlan1 = [
			"name"			    => $this->name . " VLAN 1 - WIRED",
			"description"	    => $this->name . " VLAN 1 - WIRED",
			"subnetMask"		=> "255.255.252.0",
			"startRange"		=> long2ip($IPV4LONG +   50),
			"endRange"		    => long2ip($IPV4LONG + 1010),
			"dhcpOptions"		=> [
                [
                    'optionId'  =>  "003",
                    'value'     =>  [long2ip($IPV4LONG +    1)],
                ],
                [
                    'optionId'  =>  "006",
                    'value'     =>  $dns,
                ],
                [
                    'optionId'  =>  "015",
                    'value'     =>  ["kiewitplaza.com"],
                ],
			],
		];
        $scopes[1] = $vlan1;
        $IPV4LONG += 1024;
        $vlan5 = [
			"name"			    => $this->name . " VLAN 5 - WIRELESS",
			"description"	    => $this->name . " VLAN 5 - WIRELESS",
			"subnetMask"		=> "255.255.252.0",
			"startRange"		=> long2ip($IPV4LONG +   10),
			"endRange" 		    => long2ip($IPV4LONG + 1010),
            "dhcpOptions"		=> [
                [
                    'optionId'  =>  "003",
                    'value'     =>  [long2ip($IPV4LONG +    1)],
                ],
                [
                    'optionId'  =>  "006",
                    'value'     =>  $dns,
                ],
                [
                    'optionId'  =>  "015",
                    'value'     =>  ["kiewitplaza.com"],
                ],
			],
        ];
        $scopes[5] = $vlan5;
        $IPV4LONG += 1024;
        $vlan9 = [
			"name"			    => $this->name . " VLAN 9 - VOICE",
			"description"	    => $this->name . " VLAN 9 - VOICE",
			"subnetMask"		=> "255.255.252.0",
			"startRange"		=> long2ip($IPV4LONG +   10),
			"endRange"		    => long2ip($IPV4LONG + 1010),
			"dhcpOptions"		=> [
                [
                    'optionId'  =>  "003",
                    'value'     =>  [long2ip($IPV4LONG +    1)],
                ],
                [
                    'optionId'  =>  "006",
                    'value'     =>  $dns,
                ],
                [
                    'optionId'  =>  "015",
                    'value'     =>  ["kiewitplaza.com"],
                ],
                [
                    'optionId'  =>  "150",
                    'value'     =>  ["10.252.11.14","10.252.22.14"],
                ],
			],
        ];
        $scopes[9] = $vlan9;
        $IPV4LONG += 1024;
        $vlan13 = [
			"name"			=> $this->name . " VLAN 13 - RESTRICTED",
			"description"	=> $this->name . " VLAN 13 - RESTRICTED",
			"subnetMask" => "255.255.254.0",
			"startRange" => long2ip($IPV4LONG +   10),
			"endRange"  => long2ip($IPV4LONG +  500),
            "dhcpOptions"		=> [
                [
                    'optionId'  =>  "003",
                    'value'     =>  [long2ip($IPV4LONG +    1)],
                ],
                [
                    'optionId'  =>  "006",
                    'value'     =>  ["10.251.12.189","10.251.12.190"],
                ],
                [
                    'optionId'  =>  "015",
                    'value'     =>  ["kiewitplaza.com"],
                ],
			],
        ];
        $scopes[13] = $vlan13;
        if(!$vlan)
        {
            return $scopes;
        } elseif($vlan && isset($scopes[$vlan])){
            return $scopes[$vlan];
        } else {
            return null;
        }
    }

    public function deployDhcpScope($vlan)
    {
        if(!$vlan)
        {
            return null;
        }
        $params = $this->generateDhcpScopes($vlan);

        try{
            $scope = Dhcp::addScope($params);
            if(isset($scope['scopeID']))
            {
                return Dhcp::make($scope);
            }
        } catch (\Exception $e) {
    
        }
    }

    public function deployDhcpScopes()
    {
        $vlans = [1,5,9,13];
        $scopes = [];
        foreach($vlans as $vlan)
        {
            $scopes[] = $this->deployDhcpScope($vlan);
        }
        return collect($scopes);
    }

/*     public function deployDhcpScopes()
    {
        $scopes = [];
        $newscopes = $this->GenerateDhcpScopes();
        foreach($newscopes as $newscope)
        {
            unset($scope);
            try{
                $scope = Dhcp::addScope($newscope);
                if(isset($scope['scopeID']))
                {
                    $scopes[] = $scope;
                }
            } catch (\Exception $e) {

            }
        }
        return collect($scopes);
    } */

    public function getDhcpScopesBySitecode()
    {
        return Dhcp::getScopesBySitecode($this->name);
    }

    public function getDhcpScopes()
    {
        $active = $this->getActivePrefixes();
        foreach($active as $prefix)
        {
            $scope = $prefix->getDhcpScope();
            if(isset($scope->scopeID))
            {
                $dhcp[] = $scope;
            }

        }
        return collect($dhcp);
    }

    public function locations()
    {
        $locations = new Locations($this->query);
        return $locations->where('site_id', $this->id)->get();
    }

    public function getDefaultLocation()
    {
        return Locations::where('site_id', $this->id)->where('ordering', 'id')->first();
    }

    public function getServiceNowLocationByName()
    {
        $snowloc = Location::where('name', $this->name)->first();
        if($snowloc)
        {
            return $snowloc;
        }        
    }

    public function getServiceNowLocationById()
    {
        if(isset($this->custom_fields->SNOW_SYSID))
        {
            $snowloc = Location::find($this->custom_fields->SNOW_SYSID);
            if($snowloc)
            {
                return $snowloc;
            }
        }
    }

    public function getServiceNowLocation()
    {
        $snowloc = $this->getServiceNowLocationById();
        if(!$snowloc)
        {
            $snowloc = $this->getServiceNowLocationByName();
        }
        return $snowloc;
    }

 }