<?php

namespace App\Models\Device\Ubiquiti;

use App\Queries\BaseQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Device\Ubiquiti\Ubiquiti as Model;
use App\Models\Device\Ubiquiti\UbiquitiResourceCollection as ResourceCollection;

class UbiquitiQuery extends BaseQuery
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