<?php

namespace App\Models\Location\Address;

use App\Queries\BaseQuery;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\Location\Address\Address as Model;
use App\Models\Location\Address\AddressResourceCollection as ResourceCollection;

class AddressQuery extends BaseQuery
{

    public static $model = Model::class;
    public static $resourceCollection = ResourceCollection::class;

    public static function parameters()
    {
        return [
            'filters'       =>  [
                AllowedFilter::exact('street_number'),
                AllowedFilter::exact('predirectional'),
                AllowedFilter::exact('street_name'),
                AllowedFilter::exact('street_suffix'),
                AllowedFilter::exact('postdirectional'),
                'secondary_unit_indicator',
                'secondary_number',
                'city',
                AllowedFilter::exact('state'),
                AllowedFilter::exact('postal_code'),
                AllowedFilter::exact('country'),
                'latitude',
                'longitude',
                AllowedFilter::exact('teams_civic_id'),
            ],
            'includes'      =>  [
                'building',
                'building.site',
                'building.rooms',
            ],
            'fields'        =>  [
                "id",
				"serial",
				"part_id",
				"vendor_id",
				"purchased_at",
				"warranty_id",
				"location_id",
				"created_at",
				"updated_at",
				"deleted_at",
				"last_online",
            ],
            'sorts'         =>  [
                'id',
                'city',
                'state',
                'postal_code',
                'country',
            ],
            'defaultSort'   =>  'id',
        ];
    }
}