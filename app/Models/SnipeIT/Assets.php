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

    public function checkoutToLocationId($locationid)
    {
        $loc = Locations::find($locationid);
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

    public function checkinCustom($locationid, $statusid)
    {
        $params = [
            'location_id'   =>  $locationid,
            'status_id'     =>  $statusid,
        ];
        $path = "hardware/{$this->id}/checkin";
        return static::getQuery()->post($params, $path);
    }

    ///hardware/byserial/:serial
    public static function getBySerial($serial)
    {
        $path = "hardware/byserial/" . $serial;
        return static::getQuery()->get($path)->first();
    }

    public static function create($params)
    {
        $path = "hardware";
        return static::getQuery()->post($params, $path);
    }

    public function update($params)
    {
        $path = "hardware/" . $serial;
        return static::getQuery()->patch($this->id, $params, $path);
    }

}