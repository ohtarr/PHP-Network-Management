<?php

namespace App\Models\SnipeIT;

use App\Models\SnipeIT\BaseModel;
use App\Models\SnipeIT\StatusLabels;
use App\Models\SnipeIT\Locations;

#[\AllowDynamicProperties]
class Assets extends BaseModel
{
    protected static $snipeitmodel = "hardware";

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
        return $this->getQuery()->httpPost($params, $path);
    }

    public function checkout($params)
    {
        $path = "hardware/{$this->id}/checkout";
        $response = $this->getQuery()->httpPost($params, $path);
        if($response->status == "success")
        {
            return $this->fresh();
        } else {
			throw new \Exception(json_encode($response, JSON_UNESCAPED_SLASHES));
        }
    }


    public function checkin($params)
    {
        $path = "hardware/{$this->id}/checkin";
/*         $params = [
            'status_id'         =>  $label->id,
        ]; */
        $response = $this->getQuery()->httpPost($params, $path);
        if($response->status == "success")
        {
            return $this->fresh();
        } else {
			throw new \Exception(json_encode($response, JSON_UNESCAPED_SLASHES));
        }
    }

    public function checkinCustom($locationid, $statusid)
    {
        $params = [
            'location_id'   =>  $locationid,
            'status_id'     =>  $statusid,
        ];
        $path = "hardware/{$this->id}/checkin";
        return static::getQuery()->httpPost($params, $path);
    }

    ///hardware/byserial/:serial
    public static function findBySerial($serial)
    {
        $path = "hardware/byserial/" . $serial;
        $response = static::getQuery()->httpGet($path);
        return $response;
        return static::hydrateMany($response->rows)->first();
    }

    ///hardware/bytag/:tag
    public static function findByTag($tag)
    {
        $path = "hardware/bytag/" . $tag;
        $response = static::getQuery()->httpGet($path);
        if(isset($response->id))
        {
            return static::hydrateOne($response);
        } else {
            return null;
        }

    }
}