<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;

#[\AllowDynamicProperties]
class Modules extends BaseModel
{
    protected $app = "dcim";
    protected $model = "modules";

}