<?php

namespace App\Models\Netbox\IPAM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\DCIM\Interfaces;

#[\AllowDynamicProperties]
class IpAddresses extends BaseModel
{
    protected $app = "ipam";
    protected $model = "ip-addresses";

    public function parent()
    {
        $query = Prefixes::where('contains',$this->cidr()['ip'])->where('ordering','_depth');
        if(isset($this->vrf->id))
        {
            $query = $query->where('vrf_id',$this->vrf->id);
        }
        return $query->get()->last();
    }

    public function cidr()
    {
        $reg = "/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/";
        preg_match($reg, $this->address, $hits);
        return ['ip' => $hits[1], 'bitmask' => $hits[2]];
    }

    public function range()
    {

    }

    public function getInterface()
    {
        if(!isset($this->assigned_object_id))
        {
            return null;
        }
        if($this->assigned_object_type != "dcim.interface")
        {
            return null;
        }
        $interface = Interfaces::find($this->assigned_object_id);
        if(isset($interface->id))
        {
            return $interface;
        }
    }
}