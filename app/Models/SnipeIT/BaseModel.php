<?php

namespace App\Models\SnipeIT;

use App\Models\SnipeIT\QueryBuilder;

class BaseModel
{
    //protected $app;
    //protected $model;
    protected static $snipeitmodel;
    protected $qb;
    protected $search;

    public static function getModel()
    {
        return static::$snipeitmodel;
    }

    public static function getQuery()
    {
        $qb = new QueryBuilder;
        $qb->model = new static;
        return $qb;
    }

    public function setQueryBuilder($qb)
    {
        $this->qb = $qb;
    }

    public function buildUrl()
    {
        return env('SNIPEIT_BASE_URL') . $this->getModel();
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

    public function get()
    {
        if(!$this->qb)
        {
            $this->qb = static::getQuery();
        }
        $path = static::getModel();
        $response = $this->qb->httpGet($path);
        //return $response;
        return static::hydrateMany($response->rows);
    }

    public function getRaw()
    {
        if(!$this->qb)
        {
            $this->qb = static::getQuery();
        }
        $path = static::getModel();
        $response = $this->qb->httpGet($path);
        return $response;
    }

    public static function all()
    {
        $model = new static;
        try{
            return $model->get();
        } catch (\Exception $e) {
            print $e->getMessage() . PHP_EOL;
        }
    }

    public static function first()
    {
        $path = static::getModel();
        $response = $this->qb->httpGet($path);
        //return $response;
        return static::hydrateMany($response->rows)->first();
    }

    public static function find($id)
    {
        $path = static::getModel() . "/" . $id;
        $response = static::getQuery()->httpGet($path);
        if(isset($response->id))
        {
            return static::hydrateOne($response);
        } else {
            return null;
        }
    }

    public function fresh()
    {
        return static::find($this->id);
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
        $path = static::getModel();
        $response = static::getQuery()->httpPost($params, $path);
        if($response->status == "success")
        {
            if(isset($response->payload->id))
            {
                return static::hydrateOne($response->payload);
            }
        } else {
			throw new \Exception(json_encode($response, JSON_UNESCAPED_SLASHES));
        }
    }

    public function update($params)
    {
        $path = $this->getModel() . "/" . $this->id;
        $response = static::getQuery()->httpPatch($params, $path);
        if($response->status == "success")
        {
            if(isset($response->payload->id))
            {
                return static::hydrateOne($response->payload);
            }
        } else {
			throw new \Exception(json_encode($response, JSON_UNESCAPED_SLASHES));
        }
    }

    public function delete()
    {
        $path = $this->getModel() . "/" . $this->id;
        $response = static::getQuery()->httpDelete($path);
        if($response->status == "success")
        {
            return true;
        } else {
			throw new \Exception(json_encode($response, JSON_UNESCAPED_SLASHES));
        }
    }




}
