<?php

namespace App\Models\Netbox\IPAM;

use App\Models\Netbox\BaseModel;

#[\AllowDynamicProperties]
class Vrfs extends BaseModel
{
    protected $app = "ipam";
    protected $model = "vrfs";

}