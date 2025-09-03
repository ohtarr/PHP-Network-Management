<?php

namespace App\Models\LibreNMS;

use App\Models\LibreNMS\QueryBuilder;

class BaseModel
{
    protected $query;
    protected static $model;

    public static function getModel()
    {
        return static::$model;
    }

    public static function getPath()
    {
        return static::getModel();
    }

    public static function buildUrl()
    {
        return env('LIBRENMS_BASE_URL') . static::getModel() . "/";
    }

    public static function getQuery($path = null, $search = null)
    {
        if(!$path)
        {
            $path = static::getPath();
        }
        $qb = new QueryBuilder;
        $qb->model = new static;
        $qb->path = $path;
        $qb->search = $search;
        return $qb;
    }

/*     public function getSearch()
    {
        return $this->search;
    }

    public function setSearch(array $array)
    {
        $this->search = $array;
        return $this->search;
    } */

    public static function getSearchBuilder()
    {
        $search = new SearchBuilder;
        $search->model = new static;
        return $search;
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

    public static function all()
    {
        return static::getQuery()->get();
    }

    public static function find($id, $path = null)
    {
        if(!$path)
        {
            $path = static::getPath() . "/" . $id;
        }
        return static::getQuery($path)->get();
    }

    public static function where($column, $value)
    {
        return static::getSearchBuilder()->where($column, $value);
    }

/*     public static function where($column, $value)
    {
        return static::getQuery()->where($column, $value);
    } */

    public static function get($path = null, $search = null)
    {
        return static::getQuery($path, $search)->get($path);
    }
    
    public static function first($path = null, $search = null)
    {
        return static::get($path, $search)->first();
    }

    public static function create(array $params, $path = null)
    {
        return static::getQuery($path)->post($params);
    }

    public function update(array $attributes = [], array $options = [])
    {
        $path = $this->getPath() . "/" . $this->id;
        return $this->getQuery()->put($path, $attributes);
    }
}
