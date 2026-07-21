<?php

namespace App\Models\Netbox\IPAM;

use App\Models\Netbox\BaseModel;
use IPv4\Subnet as SubnetCalculator;
use App\Models\Gizmo\Dhcp;
use App\Models\Dhcp\SubnetV4;
use App\Models\Netbox\IPAM\IpAddresses;
use App\Models\Netbox\IPAM\IpRanges;
use App\Models\Netbox\IPAM\Vrfs;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\DCIM\Devices;

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

    public function network()
    {
        return SubnetCalculator::fromCidr($this->prefix)->networkAddress()->asQuads();
    }

    public function length()
    {
        return SubnetCalculator::fromCidr($this->prefix)->networkSize();
    }

    public function netmask()
    {
        return SubnetCalculator::fromCidr($this->prefix)->mask()->asQuads();
    }

    public static function getNextAvailable()
    {
        return self::where('status','available')->where('site','null')->where('mask_length', 20)->first();
    }

    public function getDhcpScope()
    {
        return $this->getKeaDhcpScope();
    }

    public function deployDhcpScope()
    {
        return $this->deployKeaDhcpScope();
    }

    public function getGizmoDhcpScope()
    {
        return Dhcp::find($this->network());
    }

    public function getKeaDhcpScope()
    {
        return SubnetV4::findBySubnet($this->network(), $this->length());
    }

    public function deployKeaDhcpScope()
    {
        $scope = null;
        $params = $this->generateKeaDhcpScopeParams();
        if(!isset($params['id']))
        {
            return null;
        }
        try{
            //SubnetV4::create expects an array of scopes...
            $scope = SubnetV4::create([$params]);
        } catch (\Exception $e) {
            print $e->getMessage();
        }
        return $scope;
    }

    public function deployGizmoDhcpScope()
    {
        $params = $this->generateGizmoDhcpScopeParams();
        if(!isset($params['startRange']))
        {
            return null;
        }
        try{
            $scope = Dhcp::addScope($params);
        } catch (\Exception $e) {
            print $e->getMessage();
        }
        return $scope;
    }

    public static function getActivePrefixContainingIp($ip)
    {
        return static::where('contains', $ip)->where('status','active')->get()->first();
    }

    public function getDhcpOverlap()
    {
        return Dhcp::findOverlap($this->network(), $this->length());
    }

    public function getParams()
    {
        $ipcalc = SubnetCalculator::fromCidr($this->prefix);
        $return['network'] = $ipcalc->NetworkPortion()->asQuads();
        $return['broadcast'] = $ipcalc->broadcastAddress()->asQuads();
        $return['bitmask'] = $ipcalc->networkSize();
        $return['netmask'] = $ipcalc->mask()->asQuads();
        $return['first_host'] = $ipcalc->minHost()->asQuads();
        $return['last_host'] = $ipcalc->maxHost()->asQuads();
        $return['gateway'] = null;
        if(isset($this->custom_fields->DEFAULT_GATEWAY->id))
        {
            $ipaddress = IpAddresses::find($this->custom_fields->DEFAULT_GATEWAY->id);
            $return['gateway'] = $ipaddress->ip() ?? null;
        }
        if(!$return['gateway'])
        {
            $return['gateway'] = $return['first_host'] ?? null;
        }
        return $return;
    }

/*     public function generateGizmoDhcpScopeParams()
    {
        $rangeparams = $this->getParams();

        $params = [
            "startRange"		=> $rangeparams['first_host'],
            "endRange"		    => $rangeparams['last_host'],
            "subnetMask"		=> $rangeparams['netmask'],
        ];
        if(isset($this->custom_fields->name))
        {
            $params['name'] = $this->custom_fields->name;
        }
        if(isset($this->custom_fields->description))
        {
            $params['description'] = $this->custom_fields->description;
        }
        if(isset($this->custom_fields->gateway))
        {
            $optionsparams[] = [
                'optionId'  =>  "3",
                'value'     =>  [$this->custom_fields->gateway],
            ];
        }
        if(isset($this->custom_fields->dns1))
        {
            $dns[] = $this->custom_fields->dns1;
        }
        if(isset($this->custom_fields->dns2))
        {
            $dns[] = $this->custom_fields->dns2;
        }
        if(isset($this->custom_fields->dns3))
        {
            $dns[] = $this->custom_fields->dns3;
        }
        if(!empty($dns))
        {
            $optionsparams[] = [
                'optionId'  =>  "6",
                'value'     =>  $dns,
            ];
        }
        $optionsparams[] = [
                'optionId'  =>  "15",
                'value'     =>  ["kiewitplaza.com"],
        ];
        if(isset($this->custom_fields->cm1))
        {
            $cm[] = $this->custom_fields->cm1;
        }
        if(isset($this->custom_fields->cm2))
        {
            $cm[] = $this->custom_fields->cm2;
        }
        if(!empty($cm))
        {
            $optionsparams[] = [
                'optionId'  =>  "150",
                'value'     =>  $cm,
            ];
        }
        if(!empty($this->custom_fields->duration))
        {
            $optionsparams[] = [
                'optionId'  =>  "51",
                'value'     =>  [strval($this->custom_fields->duration)],
            ];
        }

        $params['dhcpOptions'] = $optionsparams;
        return $params;
    } */

    public function generateDhcpScopeParams()
    {
        $prefixparams = $this->getParams();
        $startscope = long2ip(ip2long($prefixparams['first_host']) + 10);
        $params = [
            "network"       => $prefixparams['network'],
            "bitmask"       => $prefixparams['bitmask'],
            "netmask"       => $prefixparams['netmask'],
            "first_host"    => $startscope,
            "last_host"     => $prefixparams['last_host'],
            "subnetMask"    => $prefixparams['netmask'],
            "gateway"       => $prefixparams['gateway'],
        ];
        $site = $this->getSite();
        if(isset($site->name))
        {
            if(isset($site->region->name))
            {
                $params['district'] = $site->region->name;
            }
            $params['site'] = $site->name;
        }
        $params['vlan'] = 0;
        if(isset($this->role->name))
        {
            $params['role'] = $this->role->name;
            if($this->role->name == "WIRED")
            {
                $params['vlan'] = "1";
            }
            if($this->role->name == "WIRELESS")
            {
                $params['vlan'] = "5";
            }
            if($this->role->name == "VOICE")
            {
                $cm1 = env('CALLMANAGER1');
                $cm2 = env('CALLMANAGER2');
                $params['vlan'] = "9";
                $params['voice_servers'] = [$cm1, $cm2];
            }
            if($this->role->name == "RESTRICTED")
            {
                $params['vlan'] = "13";
            }
        }
        return $params;
    }

    public function generateKeaDhcpScopeParams()
    {
        $scopeparams = $this->generateDhcpScopeParams();

        $optiondata = [
            [
                'name'  =>  'routers',
                'data'  =>  $scopeparams['gateway'],
            ]
        ];
        if(isset($scopeparams['voice_servers']))
        {
            $voicearray['name'] = 'cisco-ip-phone-tftp-servers';
            $voicearray['data'] = implode(',', $scopeparams['voice_servers']);
            $optiondata[] = $voicearray;
        }

        $keaparams = [
            'id'            =>  intval(str_replace(".", "", $scopeparams['network'])),
            'subnet'        =>  $scopeparams['network'] . "/" . $scopeparams['bitmask'],
            'pools'         =>  [
                ['pool' =>  $scopeparams['first_host'] . "-" . $scopeparams['last_host']],
            ],
            'optiondata'    =>  $optiondata,
        ];
        $keaparams['usercontext']['District'] = $scopeparams['district'] ?? null;
        $keaparams['usercontext']['Site'] = $scopeparams['site'] ?? null;
        $keaparams['usercontext']['VLAN'] = $scopeparams['vlan'] ?? null;
        $keaparams['usercontext']['Function'] = $scopeparams['role'] ?? null;
        return $keaparams;
    }

    public function generateGizmoDhcpScopeParams()
    {
        $scopeparams = $this->generateDhcpScopeParams();
        $params = [
            "startRange"		=> $scopeparams['first_host'],
            "endRange"		    => $scopeparams['last_host'],
            "subnetMask"		=> $scopeparams['netmask'],
        ];
        if(isset($this->description))
        {
            $params['name'] = $this->description;
        }
        if(isset($this->description))
        {
            $params['description'] = $this->description;
        }
        $optionsparams[] = [
            'optionId'  =>  "3",
            'value'     =>  [$scopeparams['gateway']],
        ];

        $optionsparams[] = [
                'optionId'  =>  "15",
                'value'     =>  ["kiewitplaza.com"],
        ];
        if(isset($scopeparams['voice_servers']))
        {
            $optionsparams[] = [
                'optionId'  =>  "150",
                'value'     =>  $scopeparams['voice_servers'],
            ];
        }
        $params['dhcpOptions'] = $optionsparams;
        return $params;
    }

    public function getAvailableIps($qty = 50)
    {
        $max = 254;
        if($qty > $max)
        {
            $qty = $max;
        }
        $count = 0;
        $ips = [];
        $params = $this->generateDhcpScopeParams();
        $firstip = $params['first_host'];
        if(!$firstip)
        {
            return null;
        }
        $currentiplong = ip2long($firstip);
        while(count($ips) < $qty && $count < $max)
        {
            $count++;
            $currentip = long2ip($currentiplong);
            $currentiplong++;
            $device = Devices::where('cf_ip', $currentip)->first();
            if(isset($device->id))
            {
                continue;
            }

            $ips[] = $currentip;
        }
        return $ips;
    }

}