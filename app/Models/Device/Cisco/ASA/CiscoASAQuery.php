<?php

namespace App\Models\Device\Cisco\ASA;

use App\Models\Device\DeviceQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Cisco\ASA\CiscoASA as Model;
use App\Models\Device\Cisco\ASA\CiscoASAResourceCollection as ResourceCollection;

class CiscoASAQuery extends DeviceQuery
{
    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

}