<?php

namespace App\Models\Device\Cisco;

use App\Models\Device\DeviceQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Cisco\Cisco as Model;
use App\Models\Device\Cisco\CiscoResourceCollection as ResourceCollection;

class CiscoQuery extends DeviceQuery
{
    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

}