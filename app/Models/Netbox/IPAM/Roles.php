<?php

namespace App\Models\Netbox\IPAM;

use App\Models\Netbox\BaseModel;

#[\AllowDynamicProperties]
class Roles extends BaseModel
{
    protected $app = "ipam";
    protected $model = "roles";

}