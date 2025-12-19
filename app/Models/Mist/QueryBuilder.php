<?php

namespace App\Models\Mist;

use \GuzzleHttp\Client as GuzzleClient;

class QueryBuilder
{
    protected $token;
    protected $baseurl;
    protected $orgid;
    public $search = [];
    public $model;

    public function __construct()
    {
        $this->token = env('MIST_TOKEN');
        $this->baseurl = env('MIST_BASE_URL');
        $this->orgid = env('MIST_ORG_ID');
    }

    public function getGuzzleClient()
    {
        return new GuzzleClient([
            'base_uri'  =>   $this->baseurl,
            'headers'   =>  [
                'authorization' => "Token " . $this->token,
                'Accept'        => 'application/json',
                'Content-Type'  =>  'application/json',
            ],
        ]);
    }

    public function get($custompath = null, $format = 0)
    {
        if($custompath)
        {
            $path = $custompath;
        } else {
            $path = $this->model::getPath();
        }
        $client = $this->getGuzzleClient();
        $response = $client->request('get', $path, ['query' =>  $this->search]);
        $body = $response->getBody()->getContents();
        $decoded = json_decode($body);
        if($format == 1)
        {
            return $decoded;
        }
        if($format == 2)
        {
            return collect([$this->model::hydrateOne($decoded)]);
        }
        if($format == 3)
        {
            return $this->model::hydrateMany($decoded);
        }
        if(is_array($decoded))
        {
            return $this->model::hydrateMany($decoded);
        } else {
            return collect([$this->model::hydrateOne($decoded)]);
        }
    }

    public function first($custompath = null)
    {
        return $this->get($custompath)->first();
    }

    public function where($column, $value)
    {
        $this->search[$column] = $value;
        return $this;
    }

    public function post($path, $body)
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('post', $path, ['body' => json_encode($body)]);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        //return $object;
        return $this->model::hydrateOne($object);
    }

    public function put($path, $body)
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('put', $path, ['body' => json_encode($body)]);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }

    public function patch($path, $body)
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('patch', $path, ['body' => json_encode($body)]);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }

    public function delete($path)
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('delete', $path);
        $responsecode = $response->getStatusCode();
        if($responsecode == 200)
        {
            return true;
        } else {
            return false;
        }
    }
    
    public function request($verb, $path, $body)
    {
        $client = $this->getGuzzleClient();
        $response = $client->request($verb, $path, ['body' => json_encode($body)]);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }
}