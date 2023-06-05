<?php

namespace App\Models\Netbox;

use \GuzzleHttp\Client as GuzzleClient;
use App\Models\Azure\Azure;

class QueryBuilder
{
    public $authheaders;
    public $search;

    public function __construct()
    {
        $azuretoken = Azure::getToken('api://' . env('NETBOX_CLIENT_ID') . '/.default');
        $this->authheaders['Authorization'] = 'Bearer ' . $azuretoken;
        $this->authheaders['apiauthorization'] = 'Token ' . env('NETBOX_API_TOKEN');
    }

    public function get($url)
    {
        $headers = $this->authheaders;
        $guzzleparams = [
            'verb'      =>  'get',
            'url'       =>  $url,
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
        $this->search = null;
        return $object;
    }

    public function first($url)
    {
        $this->search['limit'] = 1;
        $response = $this->get($url);
        if(isset($response->results))
        {
            return $response->results[0];
        }
    }

    public function where($column, $value)
    {
        $this->search[$column] = $value;
        return $this;
    }

    public function noPaginate()
    {
        unset($this->search['limit']);
        unset($this->search['offset']);
        return $this;
    }

    public function post($url, $body)
    {
        $headers = $this->authheaders;
        $headers['Content-Type'] = 'application/json';
        $guzzleparams = [
            'verb'      =>  'post',
            'url'       =>  $url,
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
        return $object;
    }

    public function put($url, $body)
    {
        $headers = $this->authheaders;
        $headers['Content-Type'] = 'application/json';
        $guzzleparams = [
            'verb'      =>  'put',
            'url'       =>  $url,
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
        return $object;
    }

    public function patch($url, $body)
    {
        $headers = $this->authheaders;
        $headers['Content-Type'] = 'application/json';
        $guzzleparams = [
            'verb'      =>  'patch',
            'url'       =>  $url,
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
        return $object;
    }

    public function delete($url)
    {
        $headers = $this->authheaders;
        $headers['Content-Type'] = 'application/json';
        $guzzleparams = [
            'verb'      =>  'delete',
            'url'       =>  $url,
            'params'    =>  [
                'headers'   =>  $headers,
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }
}