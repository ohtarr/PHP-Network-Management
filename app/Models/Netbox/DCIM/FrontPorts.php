<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Devices;

class FrontPorts extends BaseModel
{
    protected $app = "dcim";
    protected $model = "front-ports";

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