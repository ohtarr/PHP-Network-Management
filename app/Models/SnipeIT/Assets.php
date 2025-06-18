<?php

namespace App\Models\SnipeIT;

use App\Models\SnipeIT\BaseModel;
use App\Models\SnipeIT\StatusLabels;
use App\Models\SnipeIT\Locations;

#[\AllowDynamicProperties]
class Assets extends BaseModel
{
    protected $snipeitmodel = "hardware";

    public function isActive()
    {
        if($this->deleted_at)
        {
            return false;
        } else {
            return true;
        }
    }

    public function getLocation()
    {
        if(isset($this->assigned_to->id) && isset($this->assigned_to->type))
        {
            if($this->assigned_to->type == "location")
            {
                return Locations::find($this->assigned_to->id);
            }
        }
    }

    public function checkoutToLocation($locname)
    {
        $loc = Locations::where('name',$locname)->first();
        if(!$loc)
        {
            return null;
        }
        $label = StatusLabels::where('name','Deployed')->first();
        if(!$label)
        {
            return null;
        }
        $path = "hardware/{$this->id}/checkout";
        $params = [
            'status_id'         =>  $label->id,
            'checkout_to_type'  =>  'location',
            'assigned_location' =>  $loc->id,
        ];
        return $this->getQuery()->post($params, $path);
    }

    public function checkin()
    {
        $label = StatusLabels::where('name','Deployed')->first();
        if(!$label)
        {
            return null;
        }
        $path = "hardware/{$this->id}/checkin";
        $params = [
            'status_id'         =>  $label->id,
        ];
        return $this->getQuery()->post($params, $path);
    }

}