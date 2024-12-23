<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;

#[\AllowDynamicProperties]
class DeviceTypes extends BaseModel
{
    protected $app = "dcim";
    protected $model = "device-types";
}