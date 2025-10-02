<?php

namespace App\Models\LibreNMS;

use \GuzzleHttp\Client as GuzzleClient;
use App\Models\Azure\Azure;

class QueryBuilder
{
    protected $token;
    protected $baseurl;

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

    public function get(string $path, array $query = [])
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('get', $path, ['query' =>  $query]);
        $body = $response->getBody()->getContents();
        $decoded = json_decode($body);
        return $decoded;
    }

    public function post(string $path, array $body = [])
    {
        $jsonbody = json_encode($body);
        $client = $this->getGuzzleClient();
        $response = $client->request('post', $path, ['body' => $jsonbody]);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }

    public function patch(string $path, array $body = [])
    {
        $jsonbody = json_encode($body);
        $client = $this->getGuzzleClient();
        $response = $client->request('patch', $path, ['body' => $jsonbody]);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }

    public function delete(string $path)
    {
        $client = $this->getGuzzleClient();
        $response = $client->request('delete', $path);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }
}
