<?php

namespace App\Models\Mist;

use App\Models\Mist\Device;
use App\Models\Mist\Site;
use Silber\Bouncer\Database\HasRolesAndAbilities;

class DeviceSwitch extends Device
{
    use HasRolesAndAbilities;

    public static function all($columns = [])
    {
        return Device::where('type','switch')->get();
    }

    public static function get($path = null)
    {
        return Device::where('type','switch')->get($path);
    }

    public static function first($path = null)
    {
        return static::getQuery()->where('type','switch')->first($path);
    }

    public static function where($column, $value)
    {
        return static::getQuery()->where('type','switch')->where($column, $value);
    }

}