<?php

namespace App\Models\LibreNMS;

use App\Models\LibreNMS\BaseModel;

#[\AllowDynamicProperties]
class Device extends BaseModel
{
    protected static $model = "devices";

    public static function all()
    {
        return static::hydrateMany(static::getQuery()->get(static::getPath())->devices);
    }

    public static function find($id)
    {
        return static::hydrateOne(static::getQuery()->get(static::getPath() . "/" . $id)->devices[0]);
    }

    public static function get(array $query = [])
    {
        $qb = static::getQuery();
        $response = $qb->get(static::getPath(), $query);
        return static::hydrateMany($response->devices);
    }

    public static function create(array $body)
    {
        $query = static::getQuery();
        $response = $query->post(static::getPath(), $body);
        return static::hydrateOne($response->devices[0]);
    }

    public function update(array $body)
    {
        $query = static::getQuery();
        $response = $query->patch(static::getPath() . "/" . $this->device_id, $body);
        if($response->status == "ok")
        {
            return true;
        } else {
            return false;
        }
    }

    public function delete()
    {
        if(isset($this->device_id) && $this->device_id)
        {
            $query = static::getQuery();
            $response = $query->delete(static::getPath() . "/" . $this->device_id);
            if($response->status == "ok")
            {
                return true;
            } else {
                return false;
            }
        }
    }

    public static function addByHostname($hostname)
    {
        $device = null;
        $body = [
            'hostname'  =>  $hostname,
        ];
        try {
            $device = static::create($body);
        } catch (\Exception $e) {
            //print $e->getMessage()."\n";
        }
        return $device;
    }

    public function discover()
    {

    }

    public function disableAlerting()
    {
        $body = [
            'field' =>  "ignore",
            'data'  =>  1,
        ];
        return $this->update($body);
    }

    public function enableAlerting()
    {
        $body = [
            'field' =>  "ignore",
            'data'  =>  0,
        ];
        return $this->update($body);
    }

    public function enablePolling()
    {
        $body = [
            'field' =>  "disabled",
            'data'  =>  0,
        ];
        return $this->update($body);
    }

    public function disablePolling()
    {
        $body = [
            'field' =>  "disabled",
            'data'  =>  1,
        ];
        return $this->update($body);
    }
}