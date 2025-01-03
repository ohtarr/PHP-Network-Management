<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Devices;

#[\AllowDynamicProperties]
class Interfaces extends BaseModel
{
    protected $app = "dcim";
    protected $model = "interfaces";

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

    public function polling()
    {
        if($this->custom_fields->POLLING === true)
        {
            if($parent = $this->parent())
            {
                if($parent->polling() === true)
                {
                    return true;
                }
            } elseif($device = $this->device()) {
                if($device->polling() === true)
                {
                    return true;
                }
            }
        }
        return false;
    }

    public function alerting()
    {
        if($this->custom_fields->ALERT === true)
        {
            if($parent = $this->parent())
            {
                if($parent->alerting() === true)
                {
                    return true;
                }
            } elseif($device = $this->device()) {
                if($device->alerting() === true)
                {
                    return true;
                }
            }
        }
        return false;
    }

}