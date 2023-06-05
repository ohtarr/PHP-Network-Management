<?php

namespace App\Models\Netbox;

//use Ohtarr\Netbox\QueryBuilder;
use App\Models\Netbox\QueryBuilder;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\DCIM\Locations;

class BaseModel
{
    protected $app;
    protected $model;
    protected $query;

    public function getQuery()
    {
        if($this->query)
        {
            return $this->query;
        }
        $qb = new QueryBuilder;
        $this->query = $qb;
        return $qb;
    }

    public function buildUrl()
    {
        return env('NETBOX_BASE_URL') . "/api/" . $this->app . "/" . $this->model . "/";
        //return "/api/" . $this->app . "/" . $this->model . "/";
    }

    public function hydrate($response)
    {
        $object = new static();
        foreach($response as $key => $value)
        {
            $object->$key = $value;
        }
/*         if(!$object->app)
        {
            $object->app = $this->app;
        }
        if(!$object->model)
        {
            $object->model = $this->model;
        } */

        return $object;
    }

    public function get()
    {
        $objects = [];
        $url = $this->buildUrl();
        $response = $this->getQuery()->get($url);
        foreach($response->results as $result)
        {
            $object = $this->hydrate($result);
            $objects[] = $object;
        }
        return collect($objects);
    }

    public static function all()
    {
        $model = new static;
        $model->where('limit',1000000);
        $objects = [];
        $url = $model->buildUrl();
        $response = $model->get($url);
        return $response;
        /*         do {
            if(isset($response->next))
            {
                if($response->next)
                {
                    $url = $response->next;
                }
            }
            $response = $this->query->get($url);
            foreach($response->results as $result)
            {
                $object = $this->hydrate($result);
                $objects[] = $object;
            }

        } while ($response->next); */
        return $objects;
    }

    public function first()
    {
        $this->where('limit',1);
        $objects = $this->get();
        if(isset($objects[0]))
        {
            return $objects[0];
        }
    }

    public static function find($id)
    {
        $model = new static;
        $object = $model->getQuery()->get($model->buildUrl() . $id . "/");
        //$url = $this->buildUrl() . $id . "/";
        //$object = $this->getQuery()->get($url);
        return $model->hydrate($object);
    }

    public function where($column, $value)
    {
        $this->getQuery()->where($column, $value);
        return $this;
    }

    public function limit($limit)
    {
        return $this->where('limit', $limit);
    }

    public function offset($offset)
    {
        return $this->where('offset', $offset);
    }

    public function paginate($paginate, $offset = 0)
    {
        //return $this->where('limit', $paginate)->where('offset', $offset)->get();
        return $this->limit($paginate)->offset($offset)->get();
    }

    public function noPaginate()
    {
        $this->query->noPaginate();
    }

    public static function post($body)
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
    }

    public function site()
    {
        if(isset($this->site->id))
        {
            $sites = new Sites($this->getQuery());
            $site = $sites->find($this->site->id);
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
            $locs = new Locations($this->getQuery());
            $loc = $locs->find($this->location->id);
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
