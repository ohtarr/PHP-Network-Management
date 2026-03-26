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

    public static function generateMistCustomVersions()
    {
        $custom = [];
        $types = static::where('cf_ADD_MIST_CUSTOM_VERSION', 'true')->get();
        foreach($types as $type)
        {
            if(isset($type->default_platform->name) && $type->default_platform->name)
            {
                $custom[$type->model] = $type->default_platform->name;
            }
        }
        return $custom;
    }
}