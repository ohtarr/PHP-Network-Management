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
        return $query->get()->first();
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

    public function deployDhcpScope()
    {
        $range = $this->getDhcpIpRange();
        if(isset($range->id))
        {
            return $range->deployDhcpScope();
        }
    }

    public static function getActivePrefixContainingIp($ip)
    {
        return static::where('contains', $ip)->where('status','active')->get()->first();
    }
}