<?php

namespace App\Models\Netbox;

use \GuzzleHttp\Client as GuzzleClient;
use App\Models\Azure\Azure;

class QueryBuilder
{
    public $headers;
    public $search;
    public $model;

    public function __construct()
    {
        $azuretoken = Azure::getToken('api://' . env('NETBOX_CLIENT_ID') . '/.default');
        $this->headers['Authorization'] = 'Bearer ' . $azuretoken;
        $this->headers['apiauthorization'] = 'Token ' . env('NETBOX_API_TOKEN');
    }

    public function buildUrl()
    {
        return env('NETBOX_BASE_URL') . "/api/" . $this->model->getApp() . "/" . $this->model->getModel() . "/";
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

    public function get()
    {
        $headers = $this->headers;
        $guzzleparams = [
            'verb'      =>  'get',
            'url'       =>  $this->buildUrl(),
            'params'    =>  [
                'headers'   =>  $headers,
                'query' =>  $this->search,
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $this->hydrateMany($object->results);
    }

    public function first()
    {
        $this->search['limit'] = 1;
        $response = $this->get();
        return $response->first();
        if(isset($response->results))
        {
            return hydrateOne($response->results[0]);
        }
    }

    public function find($id)
    {
        $headers = $this->headers;
        $guzzleparams = [
            'verb'      =>  'get',
            'url'       =>  $this->buildUrl() . $id,
            'params'    =>  [
                'headers'   =>  $headers,
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
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

    public function post($body)
    {
        $headers = $this->headers;
        $headers['Content-Type'] = 'application/json';
        $guzzleparams = [
            'verb'      =>  'POST',
            'url'       =>  $this->buildUrl(),
            'params'    =>  [
                'headers'   =>  $headers,
                'body' => json_encode($body),
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $this->hydrateOne($object);
    }

    public function put($id, $body)
    {
        $headers = $this->headers;
        $headers['Content-Type'] = 'application/json';
        $guzzleparams = [
            'verb'      =>  'PUT',
            'url'       =>  $this->buildUrl() . $id . "/",
            'params'    =>  [
                'headers'   =>  $headers,
                'body' => json_encode($body),
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $this->hydrateOne($object);
    }

    public function patch($id, $body)
    {
        $headers = $this->headers;
        $headers['Content-Type'] = 'application/json';
        $guzzleparams = [
            'verb'      =>  'PATCH',
            'url'       =>  $this->buildUrl() . $id . "/",
            'params'    =>  [
                'headers'   =>  $headers,
                'body' => json_encode($body),
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $this->hydrateOne($object);
    }

    public function delete($id)
    {
        $headers = $this->headers;
        $guzzleparams = [
            'verb'      =>  "DELETE",
            'url'       =>  $this->buildUrl() . $id . "/",
            'params'    =>  [
                'headers'   =>  $headers,
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $responsecode = $response->getStatusCode();
        if($responsecode == 204)
        {
            return true;
        } else {
            return false;
        }
    }
}