<?php

namespace App\Models\Device\Cisco\ASA;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Cisco\ASA\CiscoASA as Model;
use App\Models\Device\Cisco\ASA\CiscoASAQuery as Query;
use App\Models\Device\Cisco\ASA\CiscoASAResource as Resource;

class CiscoASAController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
