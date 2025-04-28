<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\DCIM\Locations;
use App\Models\ServiceNow\Location;
use IPv4\SubnetCalculator;

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
        return $this->asns;
    }

    public function getPrimaryAsn()
    {
        return $this->asns[0];
    }

    public function assignNextAvailableAsn()
    {

    }

    public function GenerateDhcpScopes()
    {
        $supernet = $this->getProvisioningSupernet();
        if(!$supernet)
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
        $IPV4LONG = ip2long($supernet->cidr()['network']);
        $SCOPES = [];

        $calc = new SubnetCalculator(long2ip($IPV4LONG),22);
        $SCOPES[long2ip($IPV4LONG)] = [
			"name"			=> $this->name . " VLAN 1 - WIRED",
			"description"	=> $this->name . " VLAN 1 - WIRED",
			"network"		=> long2ip($IPV4LONG),
			"gateway"		=> long2ip($IPV4LONG +    1),
			"netmask"		=> $calc->getSubnetMask(),
			"firstip"		=> long2ip($IPV4LONG +   50),
			"lastip"		=> long2ip($IPV4LONG + 1010),
			"options"		=> [
				"003"	=>	[long2ip($IPV4LONG +    1)],
				"006"	=>	$dns,
				//"051"	=>	["691200"],
				"015"	=>	["kiewitplaza.com"],
			],
		];

        $IPV4LONG += 1024;
        $calc = new SubnetCalculator(long2ip($IPV4LONG),22);
		$SCOPES[long2ip($IPV4LONG)] = [
			"name"			=> $this->name . " VLAN 5 - WIRELESS",
			"description"	=> $this->name . " VLAN 5 - WIRELESS",
			"network"		=> long2ip($IPV4LONG),
			"gateway"		=> long2ip($IPV4LONG +    1),
			"netmask"		=> $calc->getSubnetMask(),
			"firstip"		=> long2ip($IPV4LONG +   10),
			"lastip" 		=> long2ip($IPV4LONG + 1010),
			"options"		=>	[
				"003" =>	[long2ip($IPV4LONG +    1)],
				"006"	=>	$dns,
				//"051"	=>	["36000"],
				"015"	=>	["kiewitplaza.com"],
			],
		];

        $IPV4LONG += 1024;
        $calc = new SubnetCalculator(long2ip($IPV4LONG),22);
		$SCOPES[long2ip($IPV4LONG)] = [
			"name"			=> $this->name . " VLAN 9 - VOICE",
			"description"	=> $this->name . " VLAN 9 - VOICE",
			"network"		=> long2ip($IPV4LONG),
			"gateway"		=> long2ip($IPV4LONG +    1),
			"netmask"		=> $calc->getSubnetMask(),
			"firstip"		=> long2ip($IPV4LONG +   10),
			"lastip"		=> long2ip($IPV4LONG + 1010),
			"options"		=>	[ // 150 is TFTP server list, comma separated
				"003"	=>	[long2ip($IPV4LONG +    1)],
				"006"	=>	$dns,
				"150"	=>	["10.252.11.14","10.252.22.14"],
				//"051"	=>	["691200"],
				"015"	=>	["kiewitplaza.com"],
			],
		];

        $IPV4LONG += 1024;
        $calc = new SubnetCalculator(long2ip($IPV4LONG),23);
		$SCOPES[long2ip($IPV4LONG)] = [
			"name"			=> $this->name . " VLAN 13 - GUEST_PARTNER_JV",
			"description"	=> $this->name . " VLAN 13 - GUEST_PARTNER_JV",
			"network" => long2ip($IPV4LONG),
			"gateway" => long2ip($IPV4LONG +    1),
			"netmask" => $calc->getSubnetMask(),
			"firstip" => long2ip($IPV4LONG +   10),
			"lastip"  => long2ip($IPV4LONG +  500),
			"options"   =>	[ // 006 is dns servers
				"003"	=>	[long2ip($IPV4LONG +    1)],
				"006"	=>	["10.251.12.189","10.251.12.190"],
				//"051"	=>	["691200"],
				"015"	=>	["kiewitplaza.com"],
			]
		];
		return $SCOPES;
    }

    public function locations()
    {
        $locations = new Locations($this->query);
        return $locations->where('site_id', $this->id)->get();
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