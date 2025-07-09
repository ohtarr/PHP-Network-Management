<?php

namespace App\Models\Netbox\IPAM;

use App\Models\Netbox\BaseModel;
use IPv4\SubnetCalculator;
use App\Models\Gizmo\Dhcp;
use App\Models\Netbox\IPAM\IpAddresses;
use App\Models\Netbox\IPAM\IpRanges;
use App\Models\Netbox\IPAM\Vrfs;
use App\Models\Netbox\DCIM\Sites;

#[\AllowDynamicProperties]
class Prefixes extends BaseModel
{
    protected $app = "ipam";
    protected $model = "prefixes";

    public function parent()
    {
        $query = $this->where('contains',$this->prefix)->where('ordering','_depth');
        if(isset($this->vrf->id))
        {
            $query = $query->where('vrf_id', $this->vrf->id);
        }        
        $array = $query->get();
        $count = count($array);
        if(isset($array[$count - 2]))
        {
            return $array[$count - 2];
        }
    }

    public function children()
    {
        $query = $this->where('within',$this->prefix)->where('depth',$this->_depth + 1);
        if(isset($this->vrf->id))
        {
            $query = $query->where('vrf_id', $this->vrf->id);
        }
        return $query->get();
    }

    public function allChildren()
    {
        return $this->where('within',$this->prefix)->get();
    }

    public function getVrf()
    {
        if(isset($this->vrf->id))
        {
            return Vrfs::find($this->vrf->id);
        }
    }

    public function getIpAddresses()
    {
        $query = IpAddresses::where('parent', $this->prefix);
        if(isset($this->vrf->id))
        {
            $query = $query->where('vrf_id', $this->vrf->id);
        }
        return $query->get();
    }

    public function getIpRanges()
    {
        $query = IpRanges::where('parent', $this->prefix);
        if(isset($this->vrf->id))
        {
            $query = $query->where('vrf_id', $this->vrf->id);
        }
        return $query->get();
    }

    public function getDhcpIpRange()
    {
        $role = Roles::where('name','DHCP_SCOPE')->first();
        if(!isset($role->id))
        {
            return null;
        }
        $query = IpRanges::where('parent', $this->prefix)->where('role_id', $role->id);
        if(isset($this->vrf->id))
        {
            $query = $query->where('vrf_id', $this->vrf->id);
        }
        return $query->first();
    }

    public function getSite()
    {
        if(isset($this->scope_type) && isset($this->scope_id))
        {
            if($this->scope_type == 'dcim.site')
            {
            return Sites::find($this->scope_id);
            }
        }
    }

	public static function netmaskToBitmask($netmask)
	{
		$bits = 0;
		$netmask = explode(".", $netmask);

		foreach($netmask as $octect)
			$bits += strlen(str_replace("0", "", decbin($octect)));
		return $bits;
	}

    public function cidr()
    {
        $reg = "/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/";
        preg_match($reg, $this->prefix, $hits);
        return ['network' => $hits[1], 'bitmask' => $hits[2]];
    }

    public function network()
    {
        return $this->cidr()['network'];
    }

    public function length()
    {
        return $this->cidr()['bitmask'];
    }

    public function netmask()
    {
        $ipcalc = new SubnetCalculator($this->network(), $this->length());
        return $ipcalc->getSubnetMask();
    }

    public static function getNextAvailable()
    {
        return self::where('status','available')->where('site','null')->where('mask_length', 20)->first();
    }

    public function getDhcpScope()
    {
        return Dhcp::find($this->network());
    }

/*     public function deployDhcpScope()
    {
        if(!isset($this->site->id))
        {
            return null;
        }

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

/*     public function generateDhcpScope()
    {
        //$site = $this->site();
        //$sitesupernet = $site->getProvisioningSupernet();
        //if(!isset($sitesupernet->id))
        //{
        //    return null;
        //}
        if(!isset($this->role->id))
        {
            return null;
        }
        //if($sitesupernet->vrf->name == "V101:DATACENTER")
        if($this->vrf->name == "V101:DATACENTER")
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

        $optionsparams = [
            [
                'optionId'  =>  "3",
                'value'     =>  [long2ip(ip2long($this->network()) +   1)],
            ],
            [
                'optionId'  =>  "6",
                'value'     =>  $dns,
            ],
            [
                'optionId'  =>  "15",
                'value'     =>  ["kiewitplaza.com"],
            ],
        ];

        if($this->role->name == "WIRED")
        {
            $params = [
                "name"			    => $this->scope->name . " VLAN 1 - WIRED",
                "description"	    => $this->scope->name . " VLAN 1 - WIRED",
                "subnetMask"		=> $this->netmask(),
                "startRange"		=> long2ip(ip2long($this->network()) +   50),
                "endRange"		    => long2ip(ip2long($this->network()) + 1010),
            ];
        } elseif($this->role->name == "WIRELESS") {
            $params = [
                "name"			    => $this->scope->name . " VLAN 5 - WIRELESS",
                "description"	    => $this->scope->name . " VLAN 5 - WIRELESS",
                "subnetMask"		=> $this->netmask(),
                "startRange"		=> long2ip(ip2long($this->network()) +   10),
                "endRange" 		    => long2ip(ip2long($this->network()) + 1010),
            ];
        } elseif($this->role->name == "VOICE") {
            $params = [
                "name"			    => $this->scope->name . " VLAN 9 - VOICE",
                "description"	    => $this->scope->name . " VLAN 9 - VOICE",
                "subnetMask"		=> $this->netmask(),
                "startRange"		=> long2ip(ip2long($this->network()) +   10),
                "endRange"		    => long2ip(ip2long($this->network()) + 1010),
            ];
            $optionsparams[] = [
                'optionId'  =>  "150",
                'value'     =>  ["10.252.11.14","10.252.22.14"],
            ];
        } elseif($this->role->name == "RESTRICTED") {
            $params = [
                "name"			    => $this->scope->name . " VLAN 13 - RESTRICTED",
                "description"	    => $this->scope->name . " VLAN 13 - RESTRICTED",
                "subnetMask"        => $this->netmask(),
                "startRange"        => long2ip(ip2long($this->network()) +   10),
                "endRange"          => long2ip(ip2long($this->network()) +  500),    
            ];
        } else {
            return null;
        }
        $params['dhcpOptions'] = $optionsparams;
        return $params;
    } */

    public function deployDhcpScope()
    {
        $range = $this->getDhcpIpRange();
        if(isset($range->id))
        {
            return $range->deployDhcpScope();
        }
    }
}