<?php

namespace App\Models\Netbox\IPAM;

use App\Models\Netbox\BaseModel;

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

    public function cidr()
    {
        $reg = "/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/";
        preg_match($reg, $this->prefix, $hits);
        return ['subnet' => $hits[1], 'bitmask' => $hits[2]];
    }
}