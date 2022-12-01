<?php

namespace App\Models\Location\Room;

use App\Queries\BaseQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Location\Room\Room as Model;
use App\Models\Location\Room\RoomResourceCollection as ResourceCollection;

class RoomQuery extends BaseQuery
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