<?php

namespace App\Models\Device\Opengear;

use App\Models\Device\DeviceQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Opengear\Opengear as Model;
use App\Models\Device\Opengear\OpengearResourceCollection as ResourceCollection;

class OpengearQuery extends DeviceQuery
{
    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

}