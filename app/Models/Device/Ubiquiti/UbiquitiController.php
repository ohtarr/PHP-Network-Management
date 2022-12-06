<?php

namespace App\Models\Device\Ubiquiti;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Ubiquiti\Ubiquiti as Model;
use App\Models\Device\Ubiquiti\UbiquitiQuery as Query;
use App\Models\Device\Ubiquiti\UbiquitiResource as Resource;

class UbiquitiController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
