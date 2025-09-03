<?php

namespace App\Models\LibreNMS;

use App\Models\LibreNMS\BaseModel;

#[\AllowDynamicProperties]
class Device extends BaseModel
{
    protected static $model = "devices";

    public static function all()
    {
        return static::get();
    }

    public static function find($id, $path = null)
    {
        if(!$path)
        {
            $path = static::getPath() . "/" . $id;
        }
        $response = static::getQuery($path)->get();
        return static::hydrateOne($response->devices[0]);
    }

    public static function get($path = null, $search = null)
    {
        $query = static::getQuery($path, $search);
        $response = $query->get();
        return static::hydrateMany($response->devices);
    }

    public static function create(array $params, $path = null)
    {
        $query = static::getQuery($path);
        $response = $query->post($params);
        return static::hydrateOne($response->devices[0]);
    }

    public function delete($path = null)
    {
        if(!$path)
        {
            $path = static::getPath() . "/" . $this->device_id;
        }
        $query = static::getQuery($path);
        return $query->delete();
    }

    public static function addByHostname($hostname)
    {
        $device = null;
        $params = [
            'hostname'  =>  $hostname,
        ];
        try {
            $device = static::create($params);
        } catch (\Exception $e) {
            //print $e->getMessage()."\n";
        }
        return $device;
    }

    public function changeIp($newip, $path = null)
    {
        if(!$path)
        {
            $path = static::getPath() . "/" . $this->device_id;
        }
        $params = [
            'field' =>  'hostname',
            'data'  =>  $newip,
        ];
        $query = static::getQuery($path);
        $response = $query->patch($params);
        return $response;
    }

    public function discover()
    {

    }

    public function findByHostname($hostname)
    {

    }

}