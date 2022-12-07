<?php

namespace App\Models\Device\Cisco\NXOS;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Cisco\NXOS\CiscoNXOS as Model;
use App\Models\Device\Cisco\NXOS\CiscoNXOSQuery as Query;
use App\Models\Device\Cisco\NXOS\CiscoNXOSResource as Resource;

class CiscoNXOSController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
