<?php

namespace App\Models\Device\Aruba;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Device\Aruba\Aruba as Model;
use App\Models\Device\Aruba\ArubaQuery as Query;
use App\Models\Device\Aruba\ArubaResource as Resource;

class ArubaController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
