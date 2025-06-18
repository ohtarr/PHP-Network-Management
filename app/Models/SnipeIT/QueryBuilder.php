<?php

namespace App\Models\SnipeIT;

use \GuzzleHttp\Client as GuzzleClient;

class QueryBuilder
{
    protected $search = [];
    public $model;

    public static function getGuzzleClient()
    {
        return new GuzzleClient([
            'base_uri'  =>   env('SNIPEIT_BASE_URL'),
            'headers'   =>  [
                'authorization' => "Bearer " . env('SNIPEIT_TOKEN'),
                'Accept'        => 'application/json',
                'Content-Type'  =>  'application/json',
            ],
        ]);
    }

    public function buildUrl()
    {
        return env('SNIPEIT_BASE_URL') . $this->model->getModel();
    }

    public function hydrateOne($data)
    {
        $object = new $this->model;
        foreach($data as $key => $value)
        {
            $object->$key = $value;
        }
        return $object;
    }

    public function hydrateMany($response)
    {
        $objects = [];
        foreach($response as $item)
        {
            $object = $this->hydrateOne($item);
            $objects[] = $object;
        }
        return collect($objects);
    }

    public function get($custompath = null)
    {
        $results = [];
        $params = [];
        $client = static::getGuzzleClient();
        if(!empty($this->search))
        {
            $params['query'] = $this->search;
        }
        if($custompath)
        {
            $path = $custompath;
        } else {
            $path = $this->buildUrl();
        }

        $response = $client->request('GET', $path, $params);
        $body = $response->getBody()->getContents();
        $objects = json_decode($body);
        return $this->hydrateMany($objects->rows);
    }

    public function first()
    {
        $this->search['limit'] = 1;
        return $this->get()->first();
    }

    public function find($id)
    {
        $client = static::getGuzzleClient();
        $response = $client->request('get', $this->buildUrl() . "/" . $id);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        if(!$object)
        {
            return null;
        }
        return $this->hydrateOne($object);
    }

    public function where($column, $value)
    {
        $this->search[$column] = $value;
        return $this;
    }

    public function limit($limit)
    {
        $this->search['limit'] = $limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->search['offset'] = $offset;
        return $this;
    }

    public function post($body, $custompath = null)
    {
        $params['body'] = json_encode($body);
        if($custompath)
        {
            $path = $custompath;
        } else {
            $path = $this->buildUrl();
        }
        $client = static::getGuzzleClient();
        $response = $client->request('post', $path, $params);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        if(isset($object->payload))
        {
            return $this->hydrateOne($object->payload);
        } else {
            return $object;
        }
    }

    public function put($id, $body, $custompath = null)
    {
        $params['body'] = json_encode($body);
        if($custompath)
        {
            $path = $custompath;
        } else {
            $path = $this->buildUrl() . "/" . $id;
        }
        $client = static::getGuzzleClient();
        $response = $client->request('put', $path, $params);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        if(isset($object->payload))
        {
            return $this->hydrateOne($object->payload);
        } else {
            return $object;
        }
    }

    public function patch($id, $body, $custompath = null)
    {
        $params['body'] = json_encode($body);
        if($custompath)
        {
            $path = $custompath;
        } else {
            $path = $this->buildUrl() . "/" . $id;
        }
        $client = static::getGuzzleClient();
        $response = $client->request('patch', $path, $params);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        if(isset($object->payload))
        {
            return $this->hydrateOne($object->payload);
        } else {
            return $object;
        }
    }

    public function delete($id, $custompath = null)
    {
        if($custompath)
        {
            $path = $custompath;
        } else {
            $path = $this->buildUrl() . "/" . $id;
        }
        $client = static::getGuzzleClient();
        $response = $client->request('delete', $path);
        $responsecode = $response->getStatusCode();
        if($responsecode == 200)
        {
            return true;
        } else {
            return false;
        }
    }
}
