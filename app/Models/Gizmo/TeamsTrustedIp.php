<?php

namespace App\Models\Gizmo;

use App\Models\Gizmo\Gizmo;
use GuzzleHttp\Client as GuzzleHttpClient;

class TeamsTrustedIp extends Gizmo
{
    public static $key = "identity";
    //public static $base_url = "";
    public static $all_url_suffix = "/api/e911/cstenanttrustedip";
    public static $get_url_suffix = "/api/e911/cstenanttrustedip";
    public static $find_url_suffix = "/api/e911/cstenanttrustedip";
    public static $save_url_suffix = "/api/e911/cstenanttrustedip";

    public $where = [];

    protected $guarded = [];

    public $queryable = [ 
        "identity",
    ];

    public $saveable = [
        "ipAddress",
        "MaskBits",
        "Description",
    ];

    //Overwrite default find method.
    public static function find($id)
    {
        //$body = [
        //    static::$key    =>  $id,
        //];
        $verb = "GET";
        $url = static::$base_url . static::$find_url_suffix . "/" . $id;
        $params = [
            'headers'   =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            //'body'  =>  json_encode($body),
        ];

        $client = new GuzzleHttpClient();
        //Build a Guzzle POST request
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response,true);
        //print_r($array);
        return static::make($array[0]);
    }

}
TeamsTrustedIp::init();