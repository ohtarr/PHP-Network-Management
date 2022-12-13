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
                'ip',
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
                AllowedFilter::callback('name', function($query, $value){
                    $query->where('data->name', 'LIKE', '%' . $value . '%');
                }),
                AllowedFilter::callback('model', function($query, $value){
                    $query->where('data->model', 'LIKE', '%' . $value . '%');
                }),
                AllowedFilter::callback('serial', function($query, $value){
                    $query->where('data->serial', 'LIKE', '%' . $value . '%');
                }),
                AllowedFilter::callback('run', function($query, $value){
                    $query->where('data->run', 'LIKE', '%' . $value . '%');
                }),
                AllowedFilter::callback('version', function($query, $value){
                    $query->where('data->version', 'LIKE', '%' . $value . '%');
                }),
                AllowedFilter::callback('inventory', function($query, $value){
                    $query->where('data->inventory', 'LIKE', '%' . $value . '%');
                }),
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