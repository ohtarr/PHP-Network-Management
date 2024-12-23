<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Locations;

#[\AllowDynamicProperties]
class Racks extends BaseModel
{
    protected $app = "dcim";
    protected $model = "racks";

   public function location()
    {
        return Locations::find($this->location->id);
    }

/*     public function devices()
    {
        return Devices::where();
    } */

}