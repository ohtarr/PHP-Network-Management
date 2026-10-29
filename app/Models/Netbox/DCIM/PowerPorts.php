<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Devices;

#[\AllowDynamicProperties]
class PowerPorts extends BaseModel
{
    protected $app = "dcim";
    protected $model = "power-ports";

    public function device()
    {
        return Devices::where('id',$this->device->id);
    }

}