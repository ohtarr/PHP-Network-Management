<?php

namespace App\Models\Location\Address;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Location\Address\Address as Model;
use App\Models\Location\Address\AddressResource as Resource;
use App\Models\Location\Address\AddressResourceCollection as ResourceCollection;
use App\Models\Location\Address\AddressQuery as Query;

class AddressController extends ControllerTemplate
{
    public static $query = Query::class;
}
