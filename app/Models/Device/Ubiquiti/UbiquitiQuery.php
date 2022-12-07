<?php

namespace App\Models\Device\Ubiquiti;

use App\Models\Device\DeviceQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Ubiquiti\Ubiquiti as Model;
use App\Models\Device\Ubiquiti\UbiquitiResourceCollection as ResourceCollection;

class UbiquitiQuery extends DeviceQuery
{
    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

}