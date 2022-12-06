<?php

namespace App\Models\Device\Opengear;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Opengear\Opengear as Model;
use App\Models\Device\Opengear\OpengearQuery as Query;
use App\Models\Device\Opengear\OpengearResource as Resource;

class OpengearController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
