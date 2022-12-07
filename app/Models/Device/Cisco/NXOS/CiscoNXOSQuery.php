<?php

namespace App\Models\Device\Cisco\NXOS;

use App\Models\Device\DeviceQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Cisco\NXOS\CiscoNXOS as Model;
use App\Models\Device\Cisco\NXOS\CiscoNXOSResourceCollection as ResourceCollection;

class CiscoNXOSQuery extends DeviceQuery
{
    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

}