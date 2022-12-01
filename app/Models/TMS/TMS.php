<?php

/*
small library for accessing "Gizmo" API in a Laravel-esque fashion.
/**/

namespace App\Models\TMS;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client as GuzzleClient;

class TMS extends Model
{
    protected $guarded = [];

    protected $username;
    protected $password;
    protected $url;
    protected $token;

    public function __construct($url, $username, $password)
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
    }

/*     public static function init()
    {
        static::$url = env('TMS_URL');        
        static::$username = env('TMS_USERNAME');
        static::$password = env('TMS_PASSWORD');
    } */

    public static function guzzle(array $guzzleparams)
    {
        $options = [];
        $params = [];
        $verb = 'get';
        $url = '';
        if(isset($guzzleparams['options']))
        {
            $options = $guzzleparams['options'];
        }
        if(isset($guzzleparams['params']))
        {
            $params = $guzzleparams['params'];
        }
        if(isset($guzzleparams['verb']))
        {
            $verb = $guzzleparams['verb'];
        }
        if(isset($guzzleparams['url']))
        {
            $url = $guzzleparams['url'];
        }

        $client = new GuzzleClient($options);
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response,true);
        if(is_array($array))
        {
            return $array;
        } else {
            return $response;
        }
    }

    public function getToken()
    {
        if($this->token)
        {
            return $this->token;
        }
        $body = [
            'username'  =>  $this->username,
            'password'  =>  $this->password,
        ];
        $guzzleparams = [
            'verb'      =>  'post',
            'url'       =>  $this->url . 'authenticate',
            'params'    =>  [
                'headers'   =>  [
                    'Content-Type'  => 'application/json',
                ],
                'body' => json_encode($body),
                //'auth'  =>  [
                //    $this->username,
                //    $this->password,
                //],
            ]
        ];
        $response = $this->guzzle($guzzleparams);
        $this->token = $response['token'];
        return $this->token;
        //$this->token = $response['key'];
        //return $response['key'];
    }

    public function getCaElinBlockIds()
    {
        return json_decode(env('TMS_CA_ELIN_BLOCK_IDS'));
    }

    public function getCaElins()
    {
        $elinblockids = $this->getCaElinBlockIds();
        foreach($elinblockids as $elinblockid)
        {
            $guzzleparams = [
                'verb'      =>  'get',
                'url'       =>  $this->url . 'didblock/' . $elinblockid . '/dids',
                'params'    =>  [
                    'headers'   =>  [
                        'Content-Type'  => 'application/json',
                    ],
                    'query' =>  [
                        'token' =>  $this->getToken(),
                    ],
                ]
            ];
            $response = $this->guzzle($guzzleparams);
            foreach($response['dids'] as $item)
            {
                $caelins[] = $item;
            }
        }
        return collect($caelins);
    }

    public function getCaElinById($elinid)
    {
        $guzzleparams = [
            'verb'      =>  'get',
            'url'       =>  $this->url . 'did/id/' . $elinid,
            'params'    =>  [
                'headers'   =>  [
                    'Content-Type'  => 'application/json',
                ],
                'query' =>  [
                    'token' =>  $this->getToken(),
                ],
            ]
        ];
        $response = $this->guzzle($guzzleparams);
        return $response['did'];
    }

    public function getCaElinByDid($did)
    {
        $guzzleparams = [
            'verb'      =>  'get',
            'url'       =>  $this->url . 'did/number/' . $did,
            'params'    =>  [
                'headers'   =>  [
                    'Content-Type'  => 'application/json',
                ],
                'query' =>  [
                    'token' =>  $this->getToken(),
                ],
            ]
        ];
        $response = $this->guzzle($guzzleparams);
        return $response['result'];
    }

    public function getAvailableCaElin()
    {
        $elins = $this->getCaElins();
        foreach($elins as $elin)
        {
            if($elin['status'] == "available")
            {
                return $elin;                
            }
        }
    }        

    public function modifyCaElin($elinid, $name, $status, $systemid)
    {
        $body = [
            'name'      =>  $name,
            'status'    =>  $status,
            'system_id' =>  $systemid,
        ];
        $guzzleparams = [
            'verb'      =>  'put',
            'url'       =>  $this->url . 'did/' . $elinid,
            'params'    =>  [
                'headers'   =>  [
                    'Content-Type'  => 'application/json',
                ],
                'query' =>  [
                    'token' =>  $this->getToken(),
                ],
                //'form_params' =>  $body,
                'body'  =>  json_encode($body),
            ]
        ];
        $response = $this->guzzle($guzzleparams);
        return $response['did'];
        /*         foreach($response['dids'] as $item)
        {
            $caelins[] = $item;
        } */
         return collect($caelins);
    }

    public function reserveCaElin($name)
    {
        $newelin = $this->getAvailableCaElin();
        return $this->modifyCaElin($newelin['id'], $name, 'reserved', '911_ENABLE');
    }

    public function releaseCaElin($id)
    {
        return $this->modifyCaElin($id, '', 'available', '');
    }

}