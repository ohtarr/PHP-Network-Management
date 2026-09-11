<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;

#[\AllowDynamicProperties]
class DeviceRoles extends BaseModel
{
    protected $app = "dcim";
    protected $model = "device-roles";
}