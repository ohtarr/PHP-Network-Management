<?php

namespace App\Models\LibreNMS;

use App\Models\LibreNMS\BaseModel;

#[\AllowDynamicProperties]
class DeviceGroup extends BaseModel
{
    protected static $model = "devicegroups";

    public static function all()
    {
        return static::get();
    }

    public static function find($id, $path = null)
    {
        $groups = static::get();
        foreach($groups as $group)
        {
            if($group->id == $id)
            {
                return static::hydrateOne($group);
            }
        }
    }

    public static function get($path = null, $search = null)
    {
        $query = static::getQuery($path, $search);
        $response = $query->get();
        return static::hydrateMany($response->groups);
    }

    public static function create(array $params, $path = null)
    {
        $query = static::getQuery($path);
        $response = $query->post($params);
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

    public function delete($path = null)
    {
        if(!$path)
        {
            $path = static::getPath() . "/" . $this->id;
        }
        $query = static::getQuery($path);
        return $query->delete();
    }

}