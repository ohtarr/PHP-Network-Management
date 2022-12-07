<?php

namespace App\Models\Device\Cisco\IOS;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Cisco\IOS\CiscoIOS as Model;
use App\Models\Device\Cisco\IOS\CiscoIOSQuery as Query;
use App\Models\Device\Cisco\IOS\CiscoIOSResource as Resource;

class CiscoIOSController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
