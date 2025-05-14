<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;

#[\AllowDynamicProperties]
class DeviceTypes extends BaseModel
{
    protected $app = "dcim";
    protected $model2 = "device-types";
    //public $model;

    public function getModel()
    {
        return $this->model2;
    }
}