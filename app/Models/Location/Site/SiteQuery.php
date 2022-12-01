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