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

    public function get()
    {
        $this->model->setQueryBuilder($this);
        return $this->model->get();
    }

    public function first()
    {
        $this->search['limit'] = 1;
        $this->model->setQueryBuilder($this);
        return $this->model->get()->first();
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

    public function httpGet($path)
    {
        $results = [];
        $params = [];
        $client = static::getGuzzleClient();
        if(!empty($this->search))
        {
            $params['query'] = $this->search;
        }
        $response = $client->request('GET', $path, $params);
        $body = $response->getBody()->getContents();
        return json_decode($body);
    }

    public static function httpPost($body, $path)
    {
        $params['body'] = json_encode($body);
        $client = static::getGuzzleClient();
        $response = $client->request('post', $path, $params);
        $body = $response->getBody()->getContents();
        return json_decode($body);
    }

    public static function httpPut($body, $path)
    {
        $params['body'] = json_encode($body);
        $client = static::getGuzzleClient();
        $response = $client->request('put', $path, $params);
        $body = $response->getBody()->getContents();
        return json_decode($body);
    }

    public static function httpPatch($body, $path)
    {
        $params['body'] = json_encode($body);
        $client = static::getGuzzleClient();
        $response = $client->request('patch', $path, $params);
        $body = $response->getBody()->getContents();
        return json_decode($body);
    }

    public static function httpDelete($path)
    {
        $client = static::getGuzzleClient();
        $response = $client->request('delete', $path);
        $body = $response->getBody()->getContents();
        return json_decode($body);
    }
}
