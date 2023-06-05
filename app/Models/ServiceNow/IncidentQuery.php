<?php

namespace App\Models\ServiceNow;

use App\Queries\BaseQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\ServiceNow\Incident as Model;
use App\Models\ServiceNow\IncidentResourceCollection as ResourceCollection;

class IncidentQuery extends BaseQuery
{
    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

    public static function parameters()
    {
        return [
            'filters'       =>  [
                'sys_id',
                'number',
                'short_description',
            ],
            'includes'      =>  [
            ],
            'fields'        =>  [
                'sys_id',
                'number',
                'short_description',
            ],
            'sorts'         =>  [
                'sys_id',
                'number',
            ],
            'defaultSort'   =>  'number',
        ];
    }

}