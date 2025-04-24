<?php

namespace App\Models\Netbox;

use App\Models\Netbox\QueryBuilder;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\DCIM\Locations;

class BaseModel
{
    protected $app;
    protected $model;
    protected $query;

    public function getApp()
    {
        return $this->app;
    }

    public function getModel()
    {
        return $this->model;
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

    public function save()
    {
        if(isset($this->id))
        {
            return static::getQuery()->put($this->id, $this);
        } else {
            return static::getQuery()->post($this);
        }
    }

    public static function create($params)
    {
        $model = new static;
        foreach($params as $key=>$value)
        {
            $model->$key = $value;
        }
        return $model->save();
    }

    public function update($params)
    {
        return static::getQuery()->patch($this->id, $params);
    }

    public function delete()
    {
        return static::getQuery()->delete($this->id);
    }
/*     public static function post($body)
    {
        $model = new static;
        $response = $model->getQuery()->post($model->buildUrl(), $body);
        $object = $model->hydrate($response);
        return $object;
    }

    public static function put($body)
    {
        $url = $this->buildUrl() . $this->id . "/";
        $response = $this->getQuery()->put($url, $body);
        $object = $this->hydrate($response);
        //$this->data = $object->data;
        return $object;
    }

    public function patch($body)
    {
        $url = $this->buildUrl() . $this->id . "/";
        $response = $this->getQuery()->patch($url, $body);
        $object = $this->hydrate($response);
        //$this->data = $object->data;
        return $object;
    }

    public function delete()
    {
        $url = $this->buildUrl() . $this->id . "/";
        $response = $this->getQuery()->delete($url, $this->id);
        $object = $this->hydrate($response);
        return $object;
    } */

    public function site()
    {
        if(isset($this->site->id))
        {
            $site = Sites::find($this->site->id);
            //$sites = new Sites($this->getQuery());
            //$site = $sites->find($this->site->id);
            if(isset($site->id))
            {
                return $site;
            }
        }
    }

    public function location()
    {
        if(isset($this->location->id))
        {
            $loc = Locations::find($this->location->id);
            if(isset($loc->id))
            {
                return $loc;
            }
        }
    }

    public function parent()
    {
        if(isset($this->parent->id))
        {
            $parent = $this->find($this->parent->id);
            if(isset($parent->id))
            {
                return $parent;
            }
        }
    }

    public function children()
    {
        return $this->where('parent_id', $this->id)->get();
    }

    public function coordinates()
    {
        $location = $this->location();
        if(isset($location->id))
        {
            $coordinates = $location->coordinates();
            if($coordinates)
            {
                return $coordinates;
            }
        }

        $site = $this->site();
        if(isset($site->id))
        {
            $coordinates = $site->coordinates();
            if($coordinates)
            {
                return $coordinates;
            }
        }
    }

    public function address()
    {
        $location = $this->location();
        if(isset($location->id))
        {
            $address = $location->address();
            if($address)
            {
                return $address;
            }
        }

        $site = $this->site();
        if(isset($site->id))
        {
            $address = $site->address();
            if($address)
            {
                return $address;
            }
        }        
    }

    public function polling()
    {
        if($this->custom_fields->POLLING === true)
        {
            $location = $this->location();
            if($location)
            {
                return $location->polling();
            } else {
                $site = $this->site();
                if($site)
                {
                    return $site->polling();
                } else {
                    return true;
                }
            }
        } else {
            return false;
        }
    }

}
