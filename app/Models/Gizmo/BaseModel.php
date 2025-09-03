<?php

namespace App\Models\Gizmo;

use GuzzleHttp\Client as GuzzleHttpClient;
use App\Models\Azure\Azure;

#[\AllowDynamicProperties]
class BaseModel
{
    protected static $path;

    public static function getToken()
    {
        return Azure::getToken('api://' . env('GIZMO_CLIENT_ID') . '/.default');
    }

    public static function getBaseUrl()
    {
        return env('GIZMO_URL');
    }

    public static function getPath()
    {
        return static::$path;
    }

    public static function hydrateOne($data)
    {
        $object = new static;
        foreach($data as $key => $value)
        {
            $object->$key = $value;
        }
        return $object;
    }

    public static function hydrateMany($response)
    {
        $objects = [];
        if(!$response)
        {
            return collect($objects);
        }

        foreach($response as $item)
        {
            $object = static::hydrateOne($item);
            $objects[] = $object;
        }
        return collect($objects);
    }

    public static function request($verb, $path, $params = [])
    {
        $client = static::getGuzzleClient();
        $response = $client->request($verb, $path, $params);
        $object = json_decode($response->getBody()->getContents());
        return $object;
    }

    public static function getGuzzleClient()
    {
        return new GuzzleHttpClient([
            'base_uri'  =>   static::getBaseUrl(),
            'headers'   =>  [
                'Authorization' => 'Bearer ' . static::getToken(),
                'Accept'        => 'application/json',
                'Content-Type'  =>  'application/json',
            ],
        ]);
    }

    public static function all($zone = null)
    {
        if(!$zone)
        {
            $zone = static::getZone();
        }
        $url = static::getBaseUrl() . static::$path . "/all/" . $zone;
        $response = static::request('get', $url);
        return static::hydrateMany($response);
    }

    public static function create($hostname, $data, $zone = null)
    {
        if(!$zone)
        {
            $zone = static::getZone();
        }

        $bodyarray = [
            'hostName'      =>  $hostname,
            'recordData'    =>  $data,
            'zone'          =>  $zone,
        ];
        $bodyjson = json_encode($bodyarray);
        print $bodyjson;
        $params['body'] = $bodyjson;
        $url = static::getBaseUrl() . static::$path;
        $response = static::request('post', $url, $params);
        return static::hydrateMany($response);
    }

}