<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Devices;

#[\AllowDynamicProperties]
class RearPorts extends BaseModel
{
    protected $app = "dcim";
    protected $model = "rear-ports";

    public function device()
    {
        return Devices::find($this->device->id);
    }

    public function parent()
    {
        if(isset($this->parent->id))
        {
            return static::find($this->parent->id);
        }
    }

}