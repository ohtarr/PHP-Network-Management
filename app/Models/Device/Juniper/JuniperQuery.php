<?php

namespace App\Models\Device\Juniper;

use App\Models\Device\DeviceQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Juniper\Juniper as Model;
use App\Models\Device\Juniper\JuniperResourceCollection as ResourceCollection;

class JuniperQuery extends DeviceQuery
{
    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

}