<?php

namespace App\Models\Location\Room;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Location\Room\Room as Model;
use App\Models\Location\Room\RoomResource as Resource;
use App\Models\Location\Room\RoomResourceCollection as ResourceCollection;
use App\Models\Location\Room\RoomQuery as Query;

class RoomController extends ControllerTemplate
{
    public static $query = Query::class;
}
