<?php

namespace App\Models\Device\Cisco\IOSXR;

use App\Models\Device\DeviceQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Cisco\IOSXR\CiscoIOSXR as Model;
use App\Models\Device\Cisco\IOSXR\CiscoIOSXRResourceCollection as ResourceCollection;

class CiscoIOSXRQuery extends DeviceQuery
{
    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

}