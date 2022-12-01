<?php

namespace App\Models\Location\Building;

use App\Queries\BaseQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Location\Building\Building as Model;
use App\Models\Location\Building\BuildingResourceCollection as ResourceCollection;

class BuildingQuery extends BaseQuery
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