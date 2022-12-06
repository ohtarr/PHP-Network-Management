<?php

namespace App\Models\Device\Cisco;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Cisco\Cisco as Model;
use App\Models\Device\Cisco\CiscoQuery as Query;
use App\Models\Device\Cisco\CiscoResource as Resource;

class CiscoController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
