<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Devices;

#[\AllowDynamicProperties]
class VirtualChassis extends BaseModel
{
    protected $app = "dcim";
    protected $model = "virtual-chassis";

    public function devices()
    {
        return Devices::where('virtual_chassis_id',$this->id)->get();
    }

    public function getMaster()
    {
        if(isset($this->master->id))
        {
            return Devices::find($this->master->id);
        }
    }

    public function getIpAddress()
    {
        $master = $this->getMaster();
        if(isset($master) && $master)
        {
            $masterip = $master->getIpAddress();
            return $masterip;
        }
    }

    public function generateDnsNames()
    {
        $dnsrecords = [];
        $ip = $this->getIpAddress();
        if(!$ip)
        {
            return $dnsrecords;
        }
        $dnsrecords[] = [
            'hostname'  =>  $this->name,
            'data'      =>  $ip,
            'type'      =>  'a',
        ];
        return $dnsrecords;
    }
}