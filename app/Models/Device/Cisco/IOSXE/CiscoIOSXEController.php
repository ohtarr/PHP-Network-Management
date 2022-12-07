<?php

namespace App\Models\Device\Cisco\IOSXE;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Cisco\IOSXE\CiscoIOSXE as Model;
use App\Models\Device\Cisco\IOSXE\CiscoIOSXEQuery as Query;
use App\Models\Device\Cisco\IOSXE\CiscoIOSXEResource as Resource;

class CiscoIOSXEController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
