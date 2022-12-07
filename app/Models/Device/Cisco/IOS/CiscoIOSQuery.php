<?php

namespace App\Models\Device\Cisco\IOS;

use App\Models\Device\DeviceQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Cisco\IOS\CiscoIOS as Model;
use App\Models\Device\Cisco\IOS\CiscoIOSResourceCollection as ResourceCollection;

class CiscoIOSQuery extends DeviceQuery
{
    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

}