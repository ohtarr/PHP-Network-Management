<?php

namespace App\Models\Mist;

use Illuminate\Database\Eloquent\Model;
use App\Models\Mist\QueryBuilder;

class BaseModel extends Model
{
    protected $primaryKey = 'id2';
    protected $search;
    protected static $mistapp;
    protected static $mistmodel;

    public static function getQuery()
    {
        $qb = new QueryBuilder;
        $qb->model = new static;
        return $qb;
    }

    public static function getOrgId()
    {
        return env('MIST_ORG_ID');
    }

    public static function getApp()
    {
        return static::$mistapp;
    }

    public static function getModel()
    {
        return static::$mistmodel;
    }

    public static function getPath()
    {
        return static::getApp() . "/" . static::getOrgId() . "/" . static::getModel();
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

    public function fresh($with = [])
    {
        if(isset($this->id))
        {
            return $this->find($this->id);
        }
    }

    public static function all($columns = [])
    {
        return static::getQuery()->get();
    }
    
    public static function where($column, $value)
    {
        return static::getQuery()->where($column, $value);
    }

    public static function get($path = null)
    {
        return static::getQuery()->get($path);
    }
    
    public static function first($path = null)
    {
        return static::getQuery()->first($path);
    }

    public static function create(array $params)
    {
        return static::getQuery()->post(static::getPath(), $params);
    }

/*     public function update2($path, array $params)
    {
        return static::getQuery()->put($path, $params);
    }

    public function delete($path, array $params)
    {
        return static::getQuery()->delete($path, $params);
    } */

}
