<?php

namespace App\Models\Location\Building;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Location\Building\Building as Model;
use App\Models\Location\Building\BuildingResource as Resource;
use App\Models\Location\Building\BuildingResourceCollection as ResourceCollection;
use App\Models\Location\Building\BuildingQuery as Query;

class BuildingController extends ControllerTemplate
{
    public static $query = Query::class;
}
