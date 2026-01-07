<?php

namespace App\Models\LibreNMS;

use App\Models\LibreNMS\BaseModel;

#[\AllowDynamicProperties]
class Location extends BaseModel
{
    protected static $model = "locations";
    
    public static function all()
    {
        return static::hydrateMany(static::getQuery()->get("resources/locations")->locations);
    }

    public static function find($id)
    {
        try {
            $loc = static::hydrateOne(static::getQuery()->get("location/" . $id)->get_location);
        } catch (\Exception $e) {
            return null;
        }
        return $loc;
    }

    public static function get()
    {
        return static::all();
    }

    public static function getByName($name)
    {
        try {
            $loc = static::hydrateOne(static::getQuery()->get("location/" . $name)->get_location);
        } catch (\Exception $e) {
            return null;
        }
        return $loc;
    }

    public static function create(array $body)
    {
        $query = static::getQuery();
        try {
            $response = $query->post(static::getPath(), $body);
        } catch (\Exception $e) {
            return null;
        }
        if(isset($response->status) && $response->status == "ok")
        {
            if(isset($response->message))
            {
                if(preg_match("/Location added with id #(\d+)/", $response->message, $hits))
                {
                    return static::find($hits[1]);
                }
            }
        }
    }

    public function update(array $body)
    {
        $query = static::getQuery();
        try {
            $response = $query->patch(static::getPath() . "/" . $this->id, $body);
        } catch (\Exception $e) {
            return null;
        }
        if(isset($response->status) && $response->status == "ok")
        {
            return $this->find($this->id);
        }
    }

    public function delete()
    {
        if(isset($this->id) && $this->id)
        {
            $query = static::getQuery();
            try {
                $response = $query->delete(static::getPath() . "/" . $this->id);
            } catch (\Exception $e) {
                return null;
            }
            if($response->status == "ok")
            {
                return true;
            } else {
                return false;
            }
        }
    }

}