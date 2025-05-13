<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\DCIM\Locations;
use App\Models\ServiceNow\Location;
use IPv4\SubnetCalculator;
use App\Models\Gizmo\Dhcp;
use App\Models\Mist\Site;
use App\Models\Mist\RfTemplate;
use App\Models\Mist\NetworkTemplate;
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

    public function getWiredPrefix()
    {
        return Prefixes::where('site_id',$this->id)->where('status','active')->where('role_id',1)->first();
    }

    public function getWirelessPrefix()
    {
        return Prefixes::where('site_id',$this->id)->where('status','active')->where('role_id',2)->first();
    }

    public function getVoicePrefix()
    {
        return Prefixes::where('site_id',$this->id)->where('status','active')->where('role_id',3)->first();
    }

    public function getRestrictedPrefix()
    {
        return Prefixes::where('site_id',$this->id)->where('status','active')->where('role_id',4)->first();
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
        if(isset($supernet->vrf->id))
        {
            $vrfid = $supernet->vrf->id;
        } else {
            $vrfid = 2;
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
                "vrf"           => $vrfid,
            ],
            5   =>  [
                "network"		=> long2ip($IPV4LONG + 1024),
                "netmask"		=> "255.255.252.0",
                "bitmask"       => 22,
                "description"	=> $this->name . " VLAN 5 - WIRELESS",
                "status"        => "active",
                "role"          => 2,
                "vrf"           => $vrfid,
            ],
            9   =>  [
                "network"		=> long2ip($IPV4LONG + 2048),
                "netmask"		=> "255.255.252.0",
                "bitmask"       => 22,
                "description"	=> $this->name . " VLAN 9 - VOICE",
                "status"        => "active",
                "role"          => 3,
                "vrf"           => $vrfid,
            ],
            13   =>  [
                "network"		=> long2ip($IPV4LONG + 3072),
                "netmask"		=> "255.255.254.0",
                "bitmask"       => 23,
                "description"	=> $this->name . " VLAN 13 - RESTRICTED",
                "status"        => "active",
                "role"          => 4,
                "vrf"           => $vrfid,
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

    public function deployActivePrefix($vlan)
    {
        $provprefix = $this->generateSitePrefix($vlan);

        $params = [
            'site'          =>  $this->id,
            'prefix'        =>  $provprefix['network'] . "/" . $provprefix['bitmask'],
            'status'        =>  $provprefix['status'],
            'description'   =>  $provprefix['description'],
            'role'          =>  $provprefix['role'],
            'vrf'           =>  $provprefix['vrf'],
        ];
        $existing = Prefixes::where("prefix", $provprefix['network'] . "/" . $provprefix['bitmask'])->where('vrf_if',$provprefix['vrf'])->first();
        if(isset($existing->id))
        {
            $prefix = $existing;
        } else {
            $prefix = Prefixes::create($params);
        }
        return $prefix;
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
        $roleid = $this->vlanToRoleMapping[$vlan];
        $prefix = Prefixes::where('site_id',$this->id)->where('status','active')->where('role_id',$roleid)->first();
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

    public function generateMistSiteVariables()
    {
        $subnets = $this->generateSitePrefix();
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
		if($snowloc['u_network_demob_date'])
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
		
		foreach($sitegroupids as $sitegroupid)
		{
            $mistsite = $mistsite->addToSiteGroup($sitegroupid);
        }
        $mistsite->updateSettings($mistsettings);
		return $mistsite;
	}
 }