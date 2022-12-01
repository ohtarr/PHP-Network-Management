<?php

namespace App\Models\Device;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Device as Model;
use App\Models\Device\DeviceResource as Resource;
use App\Models\Device\DeviceResourceCollection as ResourceCollection;
use App\Models\Device\DeviceQuery as Query;

class DeviceController extends ControllerTemplate
{
    public static $query = Query::class;
}
