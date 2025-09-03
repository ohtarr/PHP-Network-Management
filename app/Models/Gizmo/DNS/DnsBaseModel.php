<?php

namespace App\Models\Gizmo\DNS;

use App\Models\Gizmo\BaseModel;

//#[\AllowDynamicProperties]
class DnsBaseModel extends BaseModel
{
    public static function getZone()
    {
        return env('DNSZONE');
    }

    public static function all($zone = null)
    {
        if(!$zone)
        {
            $zone = static::getZone();
        }
        $path = static::$path . "/all/" . $zone;
        $response = static::request('get', $path);
        return static::hydrateMany($response);
    }

    public static function findByName($name, $zone = null)
    {
        if(!$zone)
        {
            $zone = static::getZone();
        }
        $path = static::$path . "/" . $name;
        $response = static::request('get', $path);
        return static::hydrateMany($response);
    }

    public static function findByExactName($name, $zone = null)
    {
        if(!$zone)
        {
            $zone = static::getZone();
        }
        $all = static::all($zone);
        foreach($all as $record)
        {
            if($record->hostName == $name)
            {
                return $record;
            }
        }

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
        $params['body'] = json_encode($bodyarray);
        try{
            $response = static::request('POST', static::$path, $params);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            print_r($e);
        }
        return static::hydrateOne($response);
    }

    public function delete()
    {
        if(!$this->hostName)
        {
            return false;
        }
        if(!$this->zone)
        {
            return false;
        }
        $bodyarray = [
            'hostName'      =>  $this->hostName,
            'zone'          =>  $this->zone,
        ];
        $params['body'] = json_encode($bodyarray);
        try{
            $response = static::request('DELETE', static::$path, $params);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            print_r($e);
            return false;
        }
        return true;
    }

}