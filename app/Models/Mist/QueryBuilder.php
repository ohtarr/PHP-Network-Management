<?php

namespace App\Models\Mist;

use \GuzzleHttp\Client as GuzzleClient;

class QueryBuilder
{
    protected $token;
    protected $baseurl;
    protected $orgid;

    public function __construct()
    {
        $this->token = env('MIST_TOKEN');
        $this->baseurl = env('MIST_BASE_URL');
        $this->orgid = env('MIST_ORG_ID');
    }

    public function get($path)
    {
        $guzzleparams = [
            'verb'      =>  'get',
            'url'       =>  $this->baseurl . $path,
            'params'    =>  [
                'headers'   =>  [
                    'authorization' => "Token " . $this->token,
                    'Accept'        => 'application/json',
                ],
                //'query' =>  $this->search,
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $array = json_decode($body, true);
        return $array;
    }

    public function first($path)
    {
        $guzzleparams = [
            'verb'      =>  'get',
            'url'       =>  $this->baseurl . $path,
            'params'    =>  [
                'headers'   =>  [
                    'authorization' => "Token " . $this->token,
                    'Accept'        => 'application/json',
                ],
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $array = json_decode($body, true);
        return $array;
    }

    public function find($path)
    {
        $guzzleparams = [
            'verb'      =>  'get',
            'url'       =>  $this->baseurl . $path,
            'params'    =>  [
                'headers'   =>  [
                    'authorization' => "Token " . $this->token,
                    'Accept'        => 'application/json',
                ],
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $array = json_decode($body, true);
        return $array;
    }

    public function post($path, $body)
    {
        $guzzleparams = [
            'verb'      =>  'POST',
            'url'       =>  $this->baseurl . $path,
            'params'    =>  [
                'headers'   =>  [
                    'authorization' =>  "Token " . $this->token,
                    'Content-Type'  =>  'application/json',
                    'Accept'        =>  'application/json',
                ],
                'body' => json_encode($body),
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $array = json_decode($body, true);
        return $array;
    }

    public function put($path, $body)
    {
        $guzzleparams = [
            'verb'      =>  'PUT',
            'url'       =>  $this->baseurl . $path,
            'params'    =>  [
                'headers'   =>  [
                    'authorization' =>  "Token " . $this->token,
                    'Content-Type'  =>  'application/json',
                    'Accept'        =>  'application/json',
                ],
                'body' => json_encode($body),
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $array = json_decode($body, true);
        return $array;
    }

    public function patch($path, $body)
    {
        $guzzleparams = [
            'verb'      =>  'PATCH',
            'url'       =>  $this->baseurl . $path,
            'params'    =>  [
                'headers'   =>  [
                    'authorization' =>  "Token " . $this->token,
                    'Content-Type'  =>  'application/json',
                    'Accept'        =>  'application/json',
                ],
                'body' => json_encode($body),
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $body = $response->getBody()->getContents();
        $array = json_decode($body, true);
        return $array;
    }

    public function delete($path)
    {
        $guzzleparams = [
            'verb'      =>  "DELETE",
            'url'       =>  $this->baseurl . $path,
            'params'    =>  [
                'headers'   =>  [
                    'authorization' =>  "Token " . $this->token,
                    'Accept'        =>  'application/json',
                ],
            ],
            'options'   =>  [],
        ];
        $client = new GuzzleClient($guzzleparams['options']);
        $response = $client->request($guzzleparams['verb'], $guzzleparams['url'], $guzzleparams['params']);
        $responsecode = $response->getStatusCode();
        if($responsecode == 200)
        {
            return true;
        } else {
            return false;
        }
    }
}