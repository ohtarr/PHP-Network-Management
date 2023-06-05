<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
/* use Ohtarr\Netbox\DCIM\Locations;
use Ohtarr\Netbox\DCIM\Interfaces; */

class Devices extends BaseModel
{
    protected $app = "dcim";
    protected $model = "devices";

/*     public function location()
    {
        $locations = new Locations($this->query);
        return $locations->find($this->data->location->id);
    } */

/*     public function address()
    {
        return $this->location()->address();
    } */

/*     public function coordinates()
    {
        return $this->location()->coordinates();
    } */

/*     public function polling()
    {
        if($this->custom_fields->POLLING === true)
        {
            return $this->location()->polling();
        }
        return false;
    } */

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
        $interfaces = new Interfaces($this->query);
        return $interfaces->where('device_id', $this->id)->all();
    }

}