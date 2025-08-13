<?php

namespace App\Models\Gizmo;

use App\Models\Gizmo\Gizmo;
use GuzzleHttp\Client as GuzzleHttpClient;
use App\Models\Azure\Azure;
use IPv4\SubnetCalculator;

class Dhcp extends Gizmo
{
    //fields that are queryable by this model.
    public $queryable = [ 

    ];

    //fields that are EDITABLE by this model.
    public $saveable = [

    ];

    public static function getToken()
    {
        return Azure::getToken('api://' . env('GIZMO_CLIENT_ID') . '/.default');
    }

    //get ALL records of this model
    public static function all($columns = [])
    {
        $url = static::$base_url . "/api/dhcp/scopes";
        $client = new GuzzleHttpClient();
        $response = $client->request("GET", $url);
        //get the body contents and decode json into an array.
        $array = json_decode($response->getBody()->getContents(), true);
        $newarray = [];
        foreach($array as $item)
        {
            $newarray[] = static::make($item);
        }
        //print_r($newarray);
        $collection = collect($newarray);
        return $collection;
    }

    //Get a single record of this model.
    public static function find($scopeid)
    {
        $verb = "GET";
        $url = static::$base_url . "/api/dhcp/scope/" . $scopeid;
        $params = [
            'headers'   =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        $client = new GuzzleHttpClient();
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response,true);
        //print_r($array);
        if(isset($array))
        {
            return static::make($array);
        } else {
            return null;
        }
    }

    public static function getScopesBySitecode($sitecode)
    {
        $verb = "GET";
        $url = static::$base_url . "/api/dhcp/scope/site/" . $sitecode;
        $params = [
            'headers'   =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        $client = new GuzzleHttpClient();
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response, true);
        $newarray = [];
        foreach($array as $item)
        {
            $newarray[] = static::make($item);
        }
        //print_r($newarray);
        $collection = collect($newarray);
        return $collection;
    }
    
    public function getOptions()
    {
        $verb = "GET";
        $url = static::$base_url . "/api/dhcp/options/" . $this->scopeID;
        $params = [
            'headers'   =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        $client = new GuzzleHttpClient();
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response, true);
        return collect($array);
    }

    public function getStatistics()
    {
        $verb = "GET";
        $url = static::$base_url . "/api/dhcp/statistics/" . $this->scopeID;
        $params = [
            'headers'   =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        $client = new GuzzleHttpClient();
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response, true);
        return $array[0];
    }

    public function getReservations()
    {
        $verb = "GET";
        $url = static::$base_url . "/api/dhcp/reservations/" . $this->scopeID;
        $params = [
            'headers'   =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        $client = new GuzzleHttpClient();
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response, true);
        return collect($array);
    }

    public static function getReservationByIp($ip)
    {
        $verb = "GET";
        $url = static::$base_url . "/api/dhcp/reservation/" . $ip;
        $params = [
            'headers'   =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        $client = new GuzzleHttpClient();
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response, true);
        return $array;
    }

    public static function getReservationsByMac($mac)
    {
        $verb = "GET";
        $url = static::$base_url . "/api/dhcp/reservation/search/" . $mac;
        $params = [
            'headers'   =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        $client = new GuzzleHttpClient();
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response, true);
        return collect($array);
    }

    public function getLeases()
    {
        $verb = "GET";
        $url = static::$base_url . "/api/dhcp/leases/" . $this->scopeID;
        $params = [
            'headers'   =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        $client = new GuzzleHttpClient();
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response, true);
        return collect($array);
    }

    public static function getLeasesByMac($mac)
    {
        $verb = "GET";
        $url = static::$base_url . "/api/dhcp/leases/mac/" . $mac;
        $params = [
            'headers'   =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        $client = new GuzzleHttpClient();
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response, true);
        return collect($array);
    }

    public function getFailover()
    {
        $verb = "GET";
        $url = static::$base_url . "/api/dhcp/failover/" . $this->scopeID;
        $params = [
            'headers'   =>  [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        $client = new GuzzleHttpClient();
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response, true);
        return $array;
    }

    public function addReservation($mac, $ip, $description)
	{
		$body = [
			"scopeID"	    =>  $this->scopeID,
            "ClientId"      =>  $mac,
            "IPAddress"     =>  $ip,
            "Description"   =>  $description,
		];
		$verb = "POST";
		$url = static::$base_url . "/api/dhcp/reservation";
		
        $options = [];
		$params = [
			'headers'   =>  [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
		        'Authorization'   => 'Bearer ' . $this->getToken(),
			],
			'body'  =>  json_encode($body),
		];

        $client = new GuzzleHttpClient($options);
        //Build a Guzzle request
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response,true);
		return $array;
	}

    public function updateReservation($mac, $ip, $description)
	{
		$body = [
			"scopeID"	    =>  $this->scopeID,
            "ClientId"      =>  $mac,
            "IPAddress"     =>  $ip,
            "Description"   =>  $description,
		];
		$verb = "PATCH";
		$url = static::$base_url . "/api/dhcp/reservation";
		
        $options = [];
		$params = [
			'headers'   =>  [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
		        'Authorization'   => 'Bearer ' . $this->getToken(),
			],
			'body'  =>  json_encode($body),
		];

        $client = new GuzzleHttpClient($options);
        //Build a Guzzle request
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response,true);
		return $array;
	}

    public function deleteReservation($ip)
	{
		$body = [
            "IPAddress"     =>  $ip,
		];
		$verb = "DELETE";
		$url = static::$base_url . "/api/dhcp/reservation";
		
        $options = [];
		$params = [
			'headers'   =>  [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
		        'Authorization'   => 'Bearer ' . $this->getToken(),
			],
			'body'  =>  json_encode($body),
		];

        $client = new GuzzleHttpClient($options);
        //Build a Guzzle request
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response,true);
		return $array;
	}

    public function addFailover($failovername)
	{
		$body = [
			"scopeID"	        =>  $this->scopeID,
            "FailoverName"      =>  $failovername,
		];
		$verb = "POST";
		$url = static::$base_url . "/api/dhcp/failover";
		
        $options = [];
		$params = [
			'headers'   =>  [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
		        'Authorization'   => 'Bearer ' . $this->getToken(),
			],
			'body'  =>  json_encode($body),
		];

        $client = new GuzzleHttpClient($options);
        //Build a Guzzle request
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response,true);
		return $array;
	}

    public function deleteScopeFailover($failovername)
	{
		$body = [
			"scopeID"	        =>  $this->scopeID,
            "FailoverName"      =>  $failovername,
		];
		$verb = "DELETE";
		$url = static::$base_url . "/api/dhcp/failover";
		
        $options = [];
		$params = [
			'headers'   =>  [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
		        'Authorization'   => 'Bearer ' . $this->getToken(),
			],
			'body'  =>  json_encode($body),
		];

        $client = new GuzzleHttpClient($options);
        //Build a Guzzle request
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response,true);
		return $array;
	}


    public static function addScope($scopeparams)
	{
		$body = $scopeparams;
		$verb = "POST";
		$url =  static::$base_url . "/api/dhcp/scope";
		
        $options = [];
		$params = [
			'headers'   =>  [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
		        'Authorization'   => 'Bearer ' . self::getToken(),
				//'Authorization' => $token,
			],
			'body'  =>  json_encode($body),
		];

        $client = new GuzzleHttpClient($options);
        //Build a Guzzle request
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response,true);
		return $array;
	}

    public function delete()
	{
		$body = ["scopeID" => $this->scopeID];
		$verb = "DELETE";
		$url =  static::$base_url . "/api/dhcp/scope";
		
        $options = [];
		$params = [
			'headers'   =>  [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
		        'Authorization'   => 'Bearer ' . $this->getToken(),
			],
			'body'  =>  json_encode($body),
		];

        $client = new GuzzleHttpClient($options);
        //Build a Guzzle request
        $apiRequest = $client->request($verb, $url, $params);
        $response = $apiRequest->getBody()->getContents();
        $array = json_decode($response,true);
		return $array;
	}

    public static function findOverlap($network, $bitmask)
    {
        $overlaps = [];
        $ipcalc = new SubnetCalculator($network, $bitmask);
        $range = $ipcalc->getIPAddressRange();

        $longstart = ip2long($range[0]);
        $longend = ip2long($range[1]);

        $scopes = self::all();

        foreach($scopes as $scope){
            if(ip2long($scope->scopeID) >= $longstart && ip2long($scope->scopeID) <= $longend){
                $overlaps[] = $scope;
            }
        }
        return collect($overlaps);
    }

    public function findOption($optionid)
    {
        if(isset($this->dhcpOptions))
        {
            foreach($this->dhcpOptions as $option)
            {
                if($option['optionId'] == $optionid)
                {
                    return $option;
                }
            }
        }
    }
}
//Initialize the model with the BASE_URL from env.
Dhcp::init();