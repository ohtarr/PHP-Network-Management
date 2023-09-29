<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Locations;
use App\Models\Netbox\DCIM\Interfaces;
//use Ohtarr\Netbox\DCIM\Locations;
//use Ohtarr\Netbox\DCIM\Interfaces;

class Devices extends BaseModel
{
    protected $app = "dcim";
    protected $model = "devices";

   public function location()
    {
        return Locations::find($this->location->id);
    }

    public function address()
    {
        return $this->location()->address();
    }

    public function coordinates()
    {
        return $this->location()->coordinates();
    }

    public function polling()
    {
        if($this->custom_fields->POLLING === true)
        {
            return $this->location()->polling();
        }
        return false;
    }

    public function alerting()
    {
        if($this->custom_fields->ALERT === true)
        {
            return $this->location()->alerting();
        }
        return false;
    }

    public function interfaces()
    {
        return Interfaces::where('device_id', $this->id)->get();
    }

}