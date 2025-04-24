<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;

#[\AllowDynamicProperties]
class Regions extends BaseModel
{
    protected $app = "dcim";
    protected $model = "regions";
}