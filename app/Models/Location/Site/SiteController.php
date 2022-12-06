<?php

namespace App\Models\Location\Site;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Location\Site\Site as Model;
use App\Models\Location\Site\SiteResource as Resource;
use App\Models\Location\Site\SiteResourceCollection as ResourceCollection;
use App\Models\Location\Site\SiteQuery as Query;

class SiteController extends ControllerTemplate
{
    public static $model = Model::class;    
    public static $query = Query::class;
    public static $resource = Resource::class;

}
