<?php

namespace App\Models\Location\Site;

use App\Queries\BaseQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Location\Site\Site as Model;
use App\Models\Location\Site\SiteResourceCollection as ResourceCollection;

class SiteQuery extends BaseQuery
{

    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

    public static function parameters()
    {
        return [
            'filters'       =>  [
                'name',
                AllowedFilter::exact('street_number'),
            ],
            'includes'      =>  [
                'buildings',
                'buildings.rooms',
                'defaultbuilding',
            ],
            'fields'        =>  [
                'id',
                'name',
                'default_building_id',
                'loc_sys_id',
                "created_at",
				"updated_at",
            ],
            'sorts'         =>  [
                'id',
                'name',
                'default_building_id',
                'loc_sys_id',
            ],
            'defaultSort'   =>  'id',
        ];
    }
}