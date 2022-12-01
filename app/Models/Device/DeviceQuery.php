<?php

namespace App\Models\Device;

use App\Queries\BaseQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Device as Model;
use App\Models\Device\DeviceResourceCollection as ResourceCollection;

class DeviceQuery extends BaseQuery
{

    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

    public static function parameters()
    {
        return [
            'filters'       =>  [
            ],
            'includes'      =>  [
            ],
            'fields'        =>  [
            ],
            'sorts'         =>  [
            ],
            'defaultSort'   =>  'id',
        ];
    }
}