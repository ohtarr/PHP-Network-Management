<?php

namespace App\Models\ServiceNowV2;

use App\Models\ServiceNowV2\QueryBuilder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BaseModel
{
    protected static $table;

    public static function getTable()
    {
        return static::$table;
    }

    public static function getQuery()
    {
        $qb = new QueryBuilder;
        $qb->model = new static;
        return $qb;
    }

    public static function all()
    {
        return static::getQuery()->get();
    }

    public static function first()
    {
        return static::getQuery()->first();
    }

    public static function find($id)
    {
        return static::getQuery()->find($id);
    }

    public static function findOrFail($id)
    {
        $object = static::find($id);
        if (!$object) {
            throw new ModelNotFoundException("No query results for model [" . static::class . "] {$id}");
        }
        return $object;
    }

    public static function where($column, $operator = null, $value = null)
    {
        return static::getQuery()->where(...func_get_args());
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
        $model = new static;
        foreach ($params as $key => $value) {
            $model->$key = $value;
        }
        return $model->save();
    }

    public function save()
    {
        if (isset($this->sys_id)) {
            return static::getQuery()->put($this->sys_id, $this);
        }
        return static::getQuery()->post($this);
    }

    public function update($params)
    {
        foreach ($params as $key => $value) {
            $this->$key = $value;
        }
        return static::getQuery()->patch($this->sys_id, $params);
    }

    public function delete()
    {
        return static::getQuery()->delete($this->sys_id);
    }
}
