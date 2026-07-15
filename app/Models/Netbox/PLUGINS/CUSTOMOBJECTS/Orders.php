<?php

namespace App\Models\Netbox\PLUGINS\CUSTOMOBJECTS;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\PluginsQueryBuilder as QueryBuilder;

#[\AllowDynamicProperties]
class Orders extends BaseModel
{
    protected $app = "custom-objects";
    protected $model = "orders";

    public static function getQuery()
    {
        $qb = new QueryBuilder;
        $qb->model = new static;
        return $qb;
    }

}