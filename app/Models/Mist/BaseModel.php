<?php

namespace App\Models\Mist;

use App\Models\Mist\QueryBuilder;

class BaseModel
{
    protected $query;
    protected $search = [];

    public static function getQuery()
    {
        $qb = new QueryBuilder;
        //$qb->model = new static;
        return $qb;
    }

    public static function getOrgId()
    {
        return env('MIST_ORG_ID');
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

    public static function getOne($path)
    {
        return static::hydrateOne(static::getQuery()->first($path));
    }

    public static function getMany($path)
    {
        return static::hydrateMany(static::getQuery()->get($path));
    }

    public static function post($path, $params)
    {
        return static::hydrateOne(static::getQuery()->post($path, $params));
    }

    public static function put($path, $params)
    {
        return static::hydrateOne(static::getQuery()->put($path, $params));
    }

    public static function deleteOne($path)
    {
        return static::getQuery()->delete($path);
    }
}
