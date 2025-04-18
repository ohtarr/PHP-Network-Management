<?php

namespace App\Models\Device;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Device as Model;
use App\Models\Device\DeviceQuery as Query;
use App\Models\Device\DeviceResource as Resource;

class DeviceController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
