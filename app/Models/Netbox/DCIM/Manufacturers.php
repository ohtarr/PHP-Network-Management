<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Devices;

#[\AllowDynamicProperties]
class Manufacturers extends BaseModel
{
    protected $app = "dcim";
    protected $model = "manufacturers";

    public function getDevices()
    {
        return Devices::where('manufacturer_id', $this->id)->get();
    }
}