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

}