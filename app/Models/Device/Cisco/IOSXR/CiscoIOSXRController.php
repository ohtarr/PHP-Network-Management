<?php

namespace App\Models\Device\Cisco\IOSXR;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Cisco\IOSXR\CiscoIOSXR as Model;
use App\Models\Device\Cisco\IOSXR\CiscoIOSXRQuery as Query;
use App\Models\Device\Cisco\IOSXR\CiscoIOSXRResource as Resource;

class CiscoIOSXRController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
