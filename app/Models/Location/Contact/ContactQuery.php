<?php

namespace App\Models\Location\Contact;

use App\Queries\BaseQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Location\Contact\Contact as Model;
use App\Models\Location\Contact\ContactResourceCollection as ResourceCollection;

class ContactQuery extends BaseQuery
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