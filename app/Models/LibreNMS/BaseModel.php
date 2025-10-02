<?php

namespace App\Models\LibreNMS;

use App\Models\LibreNMS\QueryBuilder;

class BaseModel
{
    protected $query;
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
        foreach($data as $key => $value)
        {
            $object->$key = $value;
        }
        return $object;
    }

    public static function hydrateMany($response)
    {
        $objects = [];
        foreach($response as $item)
        {
            $object = static::hydrateOne($item);
            $objects[] = $object;
        }
        return collect($objects);
    }
}
