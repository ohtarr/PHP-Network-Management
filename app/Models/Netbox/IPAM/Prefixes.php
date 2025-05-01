<?php

namespace App\Models\Netbox\IPAM;

use App\Models\Netbox\BaseModel;
use IPv4\SubnetCalculator;
use App\Models\Gizmo\Dhcp;

#[\AllowDynamicProperties]
class Prefixes extends BaseModel
{
    protected $app = "ipam";
    protected $model = "prefixes";

    public function parent()
    {
        $array = $this->where('contains',$this->prefix)->get();
        $count = count($array);
        return $array[$count - 2];
    }

    public function children()
    {
        return $this->where('within',$this->prefix)->where('depth',$this->_depth + 1)->get();
    }

    public function allChildren()
    {
        return $this->where('within',$this->prefix)->get();
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
}