<?php

namespace App\Models\Device\Cisco\IOSXE;

use App\Models\Device\DeviceQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Cisco\IOSXE\CiscoIOSXE as Model;
use App\Models\Device\Cisco\IOSXE\CiscoIOSXEResourceCollection as ResourceCollection;

class CiscoIOSXEQuery extends DeviceQuery
{
    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

}