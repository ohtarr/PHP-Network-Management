<?php

namespace App\Models\Mist;

use App\Models\Mist\BaseModel;
use App\Models\Mist\Device;

class DeviceProfile extends BaseModel
{
    protected static $mistapp = "orgs";
    protected static $mistmodel = "deviceprofiles";

    public static function find($id)
    {
        $path = static::getPath() . "/" . $id;
        return static::getQuery()->get($path)->first();
    }

    public function assignToDeviceByMac($mac)
    {
        $device = Device::findByMac($mac);
        if(!isset($device->mac))
        {
            return null;
        }
        return $device->assignDeviceProfile($this->id);
    }
}