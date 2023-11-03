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
        return Device::where('type','switch');
    }

}