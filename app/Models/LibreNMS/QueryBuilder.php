<?php

namespace App\Models\LibreNMS;

use \GuzzleHttp\Client as GuzzleClient;
use App\Models\Azure\Azure;

class QueryBuilder
{
    protected $token;
    protected $baseurl;
    public $search;
    public $model;
    public $path;

    public function __construct()
    {
        $this->token = env('LIBRENMS_API_TOKEN');
        $this->baseurl = env('LIBRENMS_BASE_URL');
    }

    public function getGuzzleClient()
    {
        return new GuzzleClient([
            'base_uri'  =>   $this->baseurl,
            'headers'   =>  [
                'X-Auth-Token' => $this->token,
                'Accept'        => 'application/json',
                'Content-Type'  =>  'application/json',
            ],
        ]);
    }

    public function get()
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('get', $this->path, ['query' =>  $this->search]);
        $body = $response->getBody()->getContents();
        $decoded = json_decode($body);
        return $decoded;
    }

    public function get2()
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('get', $this->path, ['query' =>  $this->search]);
        $body = $response->getBody()->getContents();
        $decoded = json_decode($body);
        return $decoded;
    }

    public function first()
    {
        return $this->get($this->path)->first();
    }

    public function where($column, $value)
    {
        $this->search[$column] = $value;
        return $this;
    }

    public function post($body)
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('post', $this->path, ['body' => json_encode($body)]);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }

    public function put($body)
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('put', $this->path, ['body' => json_encode($body)]);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }

    public function patch($body)
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('patch', $this->path, ['body' => json_encode($body)]);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }

    public function delete()
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('delete', $this->path);
        $responsecode = $response->getStatusCode();
        if($responsecode == 200)
        {
            return true;
        } else {
            return false;
        }
    }
}
