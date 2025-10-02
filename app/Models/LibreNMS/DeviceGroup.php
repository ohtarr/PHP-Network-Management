<?php

namespace App\Models\LibreNMS;

use App\Models\LibreNMS\BaseModel;

#[\AllowDynamicProperties]
class DeviceGroup extends BaseModel
{
    protected static $model = "devicegroups";
    
    public static function all()
    {
        return static::hydrateMany(static::getQuery()->get(static::getPath())->groups);
    }

    public static function find($id)
    {
        $groups = static::all();
        return $groups->where('id', $id)->first();
    }

    public static function get()
    {
        return static::all();
    }

    public static function create(array $body)
    {
        $query = static::getQuery();
        $response = $query->post(static::getPath(), $body);
        if(isset($response->id))
        {
            return static::find($response->id);
        }
    }

    public static function createSiteGroup($sitecode)
    {
        $rulestring = "{\"condition\":\"AND\",\"rules\":[{\"id\":\"devices.sysName\",\"field\":\"devices.sysName\",\"type\":\"string\",\"input\":\"text\",\"operator\":\"begins_with\",\"value\":\"{$sitecode}\"}],\"valid\":true,\"joins\":[]}";
        $params = [
            'name'      => $sitecode,
            'desc'      => $sitecode,
            'type'      => 'dynamic',
            'rules'   => $rulestring,
        ];
        return static::create($params);
    }

    public function delete()
    {
        if(isset($this->id) && $this->id)
        {
            $query = static::getQuery();
            $response = $query->delete(static::getPath() . "/" . $this->id);
            if($response->status == "ok")
            {
                return true;
            } else {
                return false;
            }
        }
    }

}