<?php

namespace App\Models\Device\Juniper;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Juniper\Juniper as Model;
use App\Models\Device\Juniper\JuniperQuery as Query;
use App\Models\Device\Juniper\JuniperResource as Resource;

class JuniperController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
