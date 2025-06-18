<?php

namespace App\Models\SnipeIT;

use App\Models\SnipeIT\QueryBuilder;

class BaseModel
{
    //protected $app;
    //protected $model;
    protected $snipeitmodel;
    protected $query;

    public function getModel()
    {
        return $this->snipeitmodel;
    }

    public static function getQuery()
    {
        $qb = new QueryBuilder;
        $qb->model = new static;
        return $qb;
    }

    public static function get($url=null)
    {
        $query = static::getQuery();
        return $query->get($url);
    }

    public static function all()
    {
        $query = static::getQuery();
        return $query->get();
    }

    public static function first()
    {
        $query = static::getQuery();
        return $query->first();
    }

    public static function find($id)
    {
        $query = static::getQuery();
        return $query->find($id);
    }

    public static function where($column, $value)
    {
        return static::getQuery()->where($column, $value);
    }

    public static function limit($limit)
    {
        return static::getQuery()->limit($limit);
    }

    public static function offset($offset)
    {
        return static::getQuery()->offset($offset);
    }

    public static function create($params)
    {
        return static::getQuery()->post($params);
    }

    public function update($params)
    {
        return static::getQuery()->patch($this->id, $params);
    }

    public function delete()
    {
        return static::getQuery()->delete($this->id);
    }




}
