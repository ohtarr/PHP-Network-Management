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
                'id',
                'credential_id',
                'data',
                'data->name',
                'data->model',
                'data->serial',
                'data->run',
                'data->version',
                'data->inventory',
                'data->interfaces',
                'data->lldp',
            ],
            'includes'      =>  [
            ],
            'fields'        =>  [
                'id',
                'ip',
                'credential_id',
                'data',
                'data->name',
                'data->model',
                'data->serial',
                'data->run',
            ],
            'sorts'         =>  [
                'id',
                'ip',
                'data->name',
                'data->model',
                'data->serial',
            ],
            'defaultSort'   =>  'id',
        ];
    }
}