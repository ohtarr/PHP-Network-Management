<?php

namespace App\Models\Netbox\IPAM;

use App\Models\Netbox\BaseModel;

#[\AllowDynamicProperties]
class IpAddresses extends BaseModel
{
    protected $app = "ipam";
    protected $model = "ip-addresses";

    public function parent()
    {

    }

    public function cidr()
    {
        $reg = "/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/";
        preg_match($reg, $this->prefix, $hits);
        return ['network' => $hits[1], 'bitmask' => $hits[2]];
    }
}