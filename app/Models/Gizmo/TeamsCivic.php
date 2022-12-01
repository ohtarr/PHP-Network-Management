<?php

namespace App\Models\Gizmo;

use App\Models\Gizmo\Gizmo;
use App\Models\Gizmo\TeamsLocation;
use GuzzleHttp\Client as GuzzleHttpClient;
use App\Models\Location\Address;

class TeamsCivic extends Gizmo
{
    //primary_Key of model.
    public static $key = "civicAddressId";
    //url suffix to access ALL endpoint
    public static $all_url_suffix = "/api/e911/csonlinecivicaddresses";
    //url suffix to access GET endpoint
    public static $get_url_suffix = "/api/e911/csonlinecivicaddress";
    //url suffix to access FIND endpoint
    public static $find_url_suffix = "/api/e911/csonlinecivicaddress";
    //url suffix to access the SAVE endpoint
    public static $save_url_suffix = "/api/e911/csonlinecivicaddress/new";
    //url suffix to access the TEST endpoint
    public static $test_url_suffix = "/api/e911/csonlinecivicaddress/test/";

    //fields that are queryable by this model.
    public $queryable = [ 
        "CivicAddressId",
        "city",
    ];

    //fields that are EDITABLE by this model.
    public $saveable = [
        "civicAddressId",
        "CompanyName",
        "CompanyTaxId",
        "HouseNumber",
        "HouseNumberSuffix",
        "StreetName",
        "StreetSuffix",
        "PreDirectional",
        "PostDirectional",
        "City",
        "CityAlias",
        "State",
        "CountryOrRegion",
        "PostalCode",
        "Description",
        "Latitude",
        "Longitude",
        "Elin",
    ];

    public function save($options = [])
    {
        if($this->{static::$key})
        {
            print "TeamsCivic Addresses are NOT modifyable!\n";
            return null;
        }
        return parent::save();
    }

    public static function validateAddress(array $address)
    {

    }

    public function validate()
    {
        if(!$this->civicAddressId)
        {
            print "No civicAddressId found!  Unable to validate!\n";
            return false;
        }
        $verb = "GET";
        $url = static::$base_url . static::$test_url_suffix . $this->civicAddressId;
        $params = [
            'headers'   =>  [
//                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
//                'Authorization' => 'Bearer ' . $token,
            ],
//            'body'  =>  json_encode($body),
        ];

        $client = new GuzzleHttpClient();
        //Build a Guzzle POST request
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response,true);

        //success = AcceptedAsIs
        if($array == "AcceptedAsIs")
        {
            return true;
        }
        return false;
    }

    public function getTeamsLocations()
    {
        if($this->civicAddressId)
        {
            return TeamsLocation::where('civicAddressId',$this->civicAddressId)->get();
        }
        print "No civicAddressId found!\n";
        return null;
    }

    public function getTeamsDefaultLocation()
    {
        if($locations = $this->getTeamsLocations())
        {
            //return $locations->whereNull('location')->first();
            return $locations->where('locationId',$this->defaultLocationId)->first();
        }
        return null;
    }

    public function getTeamsNonDefaultLocations()
    {
        if($locations = $this->getTeamsLocations())
        {
            //return $locations->whereNotNull('location');
            return $locations->where('locationId',"!=",$this->defaultLocationId);
        }
        return null;
    }

    public function getAddress()
    {
        return Address::where('teams_civic_id',$this->civicAddressId)->first();
    }

}
//Initialize the model with the BASE_URL from env.
TeamsCivic::init();