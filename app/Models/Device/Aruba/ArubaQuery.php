<?php

namespace App\Models\Device\Aruba;

use App\Models\Device\DeviceQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Aruba\Aruba as Model;
use App\Models\Device\Aruba\ArubaResourceCollection as ResourceCollection;

class ArubaQuery extends DeviceQuery
{
    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

}