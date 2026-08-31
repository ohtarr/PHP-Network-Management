<?php

namespace App\Models\Dhcp;

use App\Models\Dhcp\QueryBuilder;
use Illuminate\Support\Collection;

class BaseModel
{
    protected static $model;

    public static function getPath()
    {
        return static::$model;
    }

    public static function getQuery()
    {
        return new QueryBuilder;
    }

    public static function hydrateOne($data)
    {
        $object = new static;
        foreach ($data as $key => $value) {
            $object->$key = $value;
        }
        return $object;
    }

    public static function hydrateMany($response)
    {
        $objects = [];
        if($response){
            foreach ($response as $item) {
                $objects[] = static::hydrateOne($item);
            }
        }
        return new Collection($objects);
    }
}
