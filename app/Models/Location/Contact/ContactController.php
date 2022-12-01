<?php

namespace App\Models\Location\Contact;

use App\Http\Controllers\ControllerTemplate;
use Illuminate\Http\Request;
use App\Models\Location\Contact\Contact as Model;
use App\Models\Location\Contact\ContactResource as Resource;
use App\Models\Location\Contact\ContactResourceCollection as ResourceCollection;
use App\Models\Location\Contact\ContactQuery as Query;

class ContactController extends ControllerTemplate
{
    public static $query = Query::class;
}
