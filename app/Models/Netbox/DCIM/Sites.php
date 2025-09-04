<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\IPAM\Vrfs;
use App\Models\Netbox\IPAM\Roles;
use App\Models\Netbox\IPAM\IpRanges;
use App\Models\Netbox\IPAM\Asns;
use App\Models\Netbox\DCIM\Locations;
use App\Models\ServiceNow\Location;
use IPv4\SubnetCalculator;
use App\Models\Gizmo\Dhcp;
use App\Models\Mist\Site;
use App\Models\Mist\RfTemplate;
use App\Models\Mist\NetworkTemplate;
use App\Models\Mist\GatewayTemplate;
use App\Models\Mist\SiteGroup;

#[\AllowDynamicProperties]
class Sites extends BaseModel
{
    protected $app = "dcim";
    protected $model = "sites";

    public function vlanToRoleMapping()
    {
        return [
            1   =>  1,
            5   =>  2,
            9   =>  3,
            13  =>  4,
        ];
    }

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

    public function devices()
    {
        return Devices::where('site_id', $this->id)->get();        
    }

    public function prefixes()
    {
        return Prefixes::where('site_id', $this->id)->get();
    }

    public function getActivePrefixes()
    {
        return Prefixes::where('site_id', $this->id)->where('status', 'active')->get();
    }

    public function getSupernets()
    {
        return Prefixes::where('site_id', $this->id)->where('role_id',6)->get();
    }

    public function getProvisioningSupernet()
    {
        return Prefixes::where('site_id', $this->id)->where('role_id',6)->where('mask_length',20)->first();
    }

    public function getWiredPrefix()
    {
        $role = Roles::where('name','WIRED')->first();
        if(!isset($role->id))
        {
            return null;
        }
        return Prefixes::where('site_id',$this->id)->where('status','active')->where('role_id', $role->id)->first();
    }

    public function getWirelessPrefix()
    {
        $role = Roles::where('name','WIRELESS')->first();
        if(!isset($role->id))
        {
            return null;
        }
        return Prefixes::where('site_id',$this->id)->where('status','active')->where('role_id', $role->id)->first();
    }

    public function getVoicePrefix()
    {
        $role = Roles::where('name','VOICE')->first();
        if(!isset($role->id))
        {
            return null;
        }
        return Prefixes::where('site_id',$this->id)->where('status','active')->where('role_id', $role->id)->first();
    }

    public function getRestrictedPrefix()
    {
        $role = Roles::where('name','RESTRICTED')->first();
        if(!isset($role->id))
        {
            return null;
        }
        return Prefixes::where('site_id',$this->id)->where('status','active')->where('role_id', $role->id)->first();
    }

    public function getAsns()
    {
        $asns = [];
        foreach($this->asns as $asn)
        {
            $asns[] = Asns::find($asn->id);
        }
        return collect($asns);

    }

    public function getPrimaryAsn()
    {
        return $this->getAsns()->first();
    }

    public function assignNextAvailableAsn()
    {

    }

    public function generateSiteNetworks($vlan = null)
    {
        $supernet = $this->getProvisioningSupernet();
        if(!isset($supernet->id))
        {
            return null;
        }
        $vrf = $supernet->getVrf();
        if(!isset($vrf->id))
        {
            $vrf = Vrfs::where('name', 'V102:OFFICE')->first();
        }

        if($vrf->name == "V101:DATACENTER")
        {
            $dns1 = '10.252.13.134';
            $dns2 = '10.252.13.133';
            $dns3 = '10.252.13.135';
        } else {
            $dns1 = '10.251.12.189';
            $dns2 = '10.251.12.190';
        }

        $IPV4LONG = ip2long($supernet->cidr()['network']);
        $params[1] = [
            "network"		=> long2ip($IPV4LONG),
            "netmask"		=> "255.255.252.0",
            "bitmask"       => 22,
            "description"	=> $this->name . " - VLAN1 - WIRED",
            "status"        => "active",
            "role"          => $this->vlanToRoleMapping()[1],
            "vrf"           => $vrf->id,
            "start_address" => long2ip($IPV4LONG + 50),
            "end_address"   => long2ip($IPV4LONG + 1010),
            "gateway"       => long2ip($IPV4LONG + 1),
            "dns1"          => $dns1,
            "dns2"          => $dns2,
        ];
        if(isset($dns3))
        {
            $params[1]['dns3'] = $dns3;
        }
        $IPV4LONG = $IPV4LONG + 1024;
        $params[5] = [
            "network"		=> long2ip($IPV4LONG),
            "netmask"		=> "255.255.252.0",
            "bitmask"       => 22,
            "description"	=> $this->name . " - VLAN5 - WIRELESS",
            "status"        => "active",
            "role"          => $this->vlanToRoleMapping()[5],
            "vrf"           => $vrf->id,
            "start_address" => long2ip($IPV4LONG + 10),
            "end_address"   => long2ip($IPV4LONG + 1010),
            "gateway"       => long2ip($IPV4LONG + 1),
            "dns1"          => $dns1,
            "dns2"          => $dns2,
        ];
        if(isset($dns3))
        {
            $params[5]['dns3'] = $dns3;
        }
        $IPV4LONG = $IPV4LONG + 1024;
        $params[9] = [
            "network"		=> long2ip($IPV4LONG),
            "netmask"		=> "255.255.252.0",
            "bitmask"       => 22,
            "description"	=> $this->name . " - VLAN9 - VOICE",
            "status"        => "active",
            "role"          => $this->vlanToRoleMapping()[9],
            "vrf"           => $vrf->id,
            "start_address" => long2ip($IPV4LONG + 10),
            "end_address"   => long2ip($IPV4LONG + 1010),
            "gateway"       => long2ip($IPV4LONG + 1),
            "dns1"          => $dns1,
            "dns2"          => $dns2,
        ];
        if(isset($dns3))
        {
            $params[9]['dns3'] = $dns3;
        }
        $params[9]['cm1'] = "10.252.11.14";
        $params[9]['cm2'] = "10.252.22.14";
        $IPV4LONG = $IPV4LONG + 1024;
        $params[13] = [
            "network"		=> long2ip($IPV4LONG),
            "netmask"		=> "255.255.254.0",
            "bitmask"       => 23,
            "description"	=> $this->name . " - VLAN13 - RESTRICTED",
            "status"        => "active",
            "role"          => $this->vlanToRoleMapping()[13],
            "vrf"           => $vrf->id,
            "start_address" => long2ip($IPV4LONG + 10),
            "end_address"   => long2ip($IPV4LONG + 500),
            "gateway"       => long2ip($IPV4LONG + 1),
            "dns1"          => $dns1,
            "dns2"          => $dns2,
        ];
        if(isset($dns3))
        {
            $params[13]['dns3'] = $dns3;
        }

        if(!$vlan)
        {
            return $params;
        } elseif($vlan && isset($params[$vlan])){
            return $params[$vlan];
        } else {
            return null;
        }
    }

    public function deployActivePrefix($vlan)
    {
        $provprefix = $this->generateSiteNetworks($vlan);

        $params = [
            //'site'          =>  $this->id,
            'scope_type'    =>  'dcim.site',
            'scope_id'      =>  $this->id,
            'prefix'        =>  $provprefix['network'] . "/" . $provprefix['bitmask'],
            'status'        =>  $provprefix['status'],
            'description'   =>  $provprefix['description'],
            'role'          =>  $provprefix['role'],
            'vrf'           =>  $provprefix['vrf'],
        ];
        $prefix = Prefixes::where("prefix", $provprefix['network'] . "/" . $provprefix['bitmask'])->where('vrf_id',$provprefix['vrf'])->first();
        if(!isset($prefix->id))
        {
            $prefix = Prefixes::create($params);
        }
        return $prefix;
    }

    public function deployIpRange($vlan)
    {
        $provprefix = $this->generateSiteNetworks($vlan);

        $role = Roles::where('name','DHCP_SCOPE')->first();
        $custom_fields['name'] = $provprefix['description'];
        $custom_fields['description'] = $provprefix['description'];
        $custom_fields['gateway'] = $provprefix['gateway'];
        $custom_fields['dns1'] = $provprefix['dns1'];
        $custom_fields['dns2'] = $provprefix['dns2'];
        if(isset($provprefix['dns3']))
        {  
            $custom_fields['dns3'] = $provprefix['dns3'];
        }
        if(isset($provprefix['cm1']))
        {  
            $custom_fields['cm1'] = $provprefix['cm1'];
        }
        if(isset($provprefix['cm2']))
        {  
            $custom_fields['cm2'] = $provprefix['cm2'];
        }
        $params = [
            'start_address'     =>  $provprefix['start_address'] . "/" . $provprefix['bitmask'],
            'end_address'       =>  $provprefix['end_address'] . "/" . $provprefix['bitmask'],
            'vrf'               =>  $provprefix['vrf'],
            'status'            =>  'active',
            'description'       =>  $provprefix['description'],
            'role'              =>  $role->id,
            'custom_fields'     =>  $custom_fields,
        ];
        $range = IpRanges::where("start_address", $provprefix['start_address'] . "/" . $provprefix['bitmask'])->where('vrf_id',$provprefix['vrf'])->first();
        if(!isset($range->id))
        {
            $range = IpRanges::create($params);
        }
        return $range;
    }

    public function deployDhcpScope($vlan)
    {
        $scope = null;
        if(!$vlan)
        {
            return null;
        }
        if(!isset($this->vlanToRoleMapping()[$vlan]))
        {
            return null;
        }
        $roleid = $this->vlanToRoleMapping()[$vlan];
        //$prefix = Prefixes::where('site_id',$this->id)->where('status','active')->where('role_id',$roleid)->first();
        $prefix = Prefixes::where('scope_type',"dcim.site")->where('scope_id', $this->id)->where('status','active')->where('role_id',$roleid)->first();
        if(!isset($prefix->id))
        {
            return null;
        }
        try{
            $scope = $prefix->deployDhcpScope();
        } catch (\Exception $e) {
            
        }
        return $scope;
    }

    public function getDhcpScopesBySitecode()
    {
        return Dhcp::getScopesBySitecode($this->name);
    }

    public function getDhcpScopes()
    {
        $dhcp = [];
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
        $snowloc = Location::where('companyISNOTEMPTY')->where('name', $this->name)->first();
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

    public function generateMistSiteVariables()
    {
        $supernet = $this->getProvisioningSupernet();
        $subnets = $this->generateSiteNetworks();
		if(!$subnets)
		{
			$msg = "Unable to generate Mist Site Variables";
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}
		$reg = "/(\d{1,3}\.\d{1,3}\.\d{1,3})\.0/";

		$variables = [
			'SITE_CODE'		    =>	strtoupper($this->name),
	        'CORP_WIFI_VLAN'   	=>  5,
			'GUEST_WIFI_VLAN'   =>  13,
   			'SITE_AGG_PREFIX'	=>	$supernet->network(),
			'SITE_AGG_MASK'		=>	$supernet->length(),
			'INT_WAN_INET_1'	=>	'ge-0/0/0',
			'INT_WAN_INET_2'	=>	'ge-0/0/15',
			'INT_WAN_KPN_1'		=>	'ge-0/0/14',
			'INT_LAN_RANGE'		=>	'ge-0/0/1-13',
		];

		foreach($subnets as $vlan => $subnet)
		{
			unset($hits);
			preg_match($reg, $subnet['network'], $hits);
			$variables['SITE_VLAN_' . $vlan . '_PREFIX'] = $hits[1];
			$variables['SITE_VLAN_' . $vlan . '_MASK'] = $subnet['bitmask'];
		}
		return $variables;
    }

    public function generateMistSiteSettings()
    {
        $variables = $this->generateMistSiteVariables();
		if(!$variables)
		{
			$msg = "Unable to generate Mist Site Settings";
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}

		$mistsettings = [
			'persist_config_on_device'  =>  1,
			'switch_mgmt'   =>  [
				'root_password' =>  env('MIST_LOCAL_ADMIN_PASSWORD'),
			],
			'gateway_mgmt'  =>  [
				'root_password' =>  env('MIST_LOCAL_ADMIN_PASSWORD'),
				'app_usage'	=>	1,
				'auto_signature_update' => [
					'enable'		=>	false,
					'time_of_day'	=>	'02:00',
					'day_of_week'	=>	'sun',
				],
			],
			'auto_upgrade'  =>  [
				'enabled'   =>  null,
				'version'   =>  'beta',
				'time_of_day'   =>  '02:00',
				'custom_versions'	=>	[],
				'day_of_week'   =>  'sun',
			],
			'rogue' => [
				'min_rssi' => -70,
				'min_duration' => 10,
				'enabled' => 1,
				'honeypot_enabled' => 1,
				'whitelisted_bssids' => [
					'0' => null,
				],
				'whitelisted_ssids' => [
						'0' => 'KiewitWLan',
						'1' => 'KiewitGuest',
						'2' => 'KiewitTV',
				],
			],
		];

		//Setup custom Variables for site.
		$mistsettings['vars'] = $variables;
		
		return $mistsettings;
    }

    public function generateMistSiteParameters()
	{
        $rftemplateid = env('MIST_RF_TEMPLATE_ID');
        $networktemplateid = env('MIST_NETWORK_TEMPLATE_ID');
        $gatewaytemplateid = env('MIST_GATEWAY_TEMPLATE_ID');
   		//Check to make sure RF template exists, if not get out of here!
        $rftemplate = RfTemplate::find($rftemplateid);
		if(!$rftemplate)
		{
			$msg = 'RFTEMPLATE does not exist!';
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}

        $networktemplate = NetworkTemplate::find($networktemplateid);
		if(!$networktemplate)
		{
			$msg = 'NETWORKTEMPLATE does not exist!';
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}

        $gatewaytemplate = GatewayTemplate::find($gatewaytemplateid);
		if(!$gatewaytemplate)
		{
			$msg = 'GATEWAYTEMPLATE does not exist!';
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}

		$snowloc = $this->getServiceNowLocation();
		if(!isset($snowloc->sys_id))
		{
			$msg = "No SNOW site found!";
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}
		if(!$snowloc->latitude || !$snowloc->longitude)
		{
			$msg = "SNOW site missing latitude/longitude!  Please ensure SNOW site has proper coordinates and try again!";
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}
		$country_format = [
			"USA"   =>  "US",
			"US"	=>	"US",
			"CAN"   =>  "CA",
			"CA"	=>	"CA",
			"MEX"   =>  "MX",
			"MX"	=>	"MX",
		];
		
		if(isset($country_format[$snowloc->country]))
		{
			$countrycode = $country_format[$snowloc->country];			
		} else {
			$msg = "Unsupported Country! (" . $snowloc->country . ")";
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}

		$params = [
			'name'  => $this->name,
			'address'   =>  '',
			'timezone'	=>	'UTC',
			'country_code'	=>	$countrycode,
			'latlng' => [
				'lat'   =>  $snowloc->latitude,
				'lng'   =>  $snowloc->longitude,
			],
			'rftemplate_id' =>  $rftemplateid,
			'networktemplate_id'    =>  $networktemplateid,
            'gatewaytemplate_id'    =>  $gatewaytemplateid,
            'sitegroup_ids'     =>  $this->generateMistSiteGroups(),
		];
		
		return $params;
	}

    public function generateMistSiteGroups()
	{
		return [
			env('MIST_SITE_GROUP_1'),
			env('MIST_SITE_GROUP_2'),
		];
	}

	public function getMistSite()
	{
		try{
            $site = Site::findByName($this->name);
		} catch (Exception $E) {
			print "Failed to get Mist Location!\n";
			return null;
		}
		return $site;
	}

    public function createMistSite()
	{
 		$mistsite = $this->getMistSite();
		if($mistsite)
		{
			$msg = "Mist Site already exists!";
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}
		$snowloc = $this->getServiceNowLocation();
		if(!$snowloc)
		{
			$msg = "No SNOW site found!";
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}
		if($snowloc->u_network_demob_date)
		{
			$msg = 'SNOW site is demobed!  The "Network Demobilization Date" MUST be blank in SNOW location!';
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}
		$mistsettings = $this->generateMistSiteSettings();
		
		if(!$mistsettings)
		{
			$msg = "Unable to generate Mist Site Settings";
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}

		$sitegroupids = $this->generateMistSiteGroups();

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
	
		$params = $this->generateMistSiteParameters();

        $mistsite = Site::create($params);
		if(!$mistsite)
		{
			$msg = "Failed to create mist site " . $this->data['sitecode'];
			print $msg . PHP_EOL;
			throw new \Exception($msg);
		}
		
//		foreach($sitegroupids as $sitegroupid)
//		{
//          $mistsite = $mistsite->addToSiteGroup($sitegroupid);
//      }
        $mistsite->updateSettings($mistsettings);
		return $mistsite;
	}
 }