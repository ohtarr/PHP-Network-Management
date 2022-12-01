<?php

namespace App\Models\Location\Address;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Models\Location\Address\AddressCollection;
use App\Models\Location\Building\Building;
use App\Models\ServiceNow\ServiceNowLocation;
use App\Models\Gizmo\TeamsCivic;
use App\Models\E911\E911Erl;

class Address extends Model
{

    protected $connection = 'mysql-whereuat';
    protected $appends = ['street1','street2'];

    public function newCollection(array $models = []) 
    { 
       return new AddressCollection($models); 
    }

    //WHEREUAT_ADDRESS to SERVICENOWLOCATION field mappings
    public $teamsAddressMapping = [
        'street_number'             => 'houseNumber',
        'predirectional'            => 'preDirectional',
        'street_name'               => 'streetName',
        'street_suffix'             => 'streetSuffix',
        'postdirectional'           => 'postDirectional',
        'city'                      => 'city',
        'state'                     => 'state',
        'postal_code'               => 'postalCode',
        'country'                   => 'countryOrRegion',
        'latitude'                  => 'latitude',
        'longitude'                 => 'longitude',
    ];

    //WHEREUAT_ADDRESS to SERVICENOWLOCATION field mappings
    public $snowAddressMapping = [
        'street_number'             => 'u_street_number',
        'predirectional'            => 'u_street_predirectional',
        'street_name'               => 'u_street_name',
        'street_suffix'             => 'u_street_suffix',
        'postdirectional'           => 'u_street_postdirectional',
        'secondary_unit_indicator'  => 'u_secondary_unit_indicator',
        'secondary_number'          => 'u_secondary_number',
        'city'                      => 'city',
        'state'                     => 'state',
        'postal_code'               => 'zip',
        'country'                   => 'country',
        'latitude'                  => 'latitude',
        'longitude'                 => 'longitude',
    ];

    //RELATIONSHIP to BUILDING
    public function building()
    {
        return $this->hasOne(Building::class);
    }

    public function getStreet1Attribute()
    {
        $array = [
            'street_number',
            'predirectional',
            'street_name',
            'street_suffix',
            'postdirectional',
        ];
        $street1 = "";
        foreach($array as $element)
        {
            if($this->$element)
            {
                if($street1)
                {
                    $street1 .= " ";
                }
                $street1 .= $this->$element;
            }
        }
        return $street1;
    }

    public function getStreet()
    {
        $array = [
            'predirectional',
            'street_name',
            'street_suffix',
            'postdirectional',
        ];
        $street1 = "";
        foreach($array as $element)
        {
            if($this->$element)
            {
                if($street1)
                {
                    $street1 .= " ";
                }
                $street1 .= $this->$element;
            }
        }
        return $street1;
    }

    public function getStreet2Attribute()
    {
        $array = [
            'secondary_unit_indicator',
            'secondary_number',
        ];
        $street2 = "";
        foreach($array as $element)
        {
            if($this->$element)
            {
                if($street2)
                {
                    $street2 .= " ";
                }
                $street2 .= $this->$element;
            }
        }
        return $street2;
    }

    //retrieve TEAMS CIVIC from TEAMs.
    public function getTeamsCivic()
    {
        if(!$this->teams_civic_id)
        {
            return null;
        }
        return TeamsCivic::find($this->teams_civic_id);
    }

    public function getSite()
    {
        if($this->site)
        {
            return $this->site;
        }
        if($this->building)
        {
            return $this->building->site;
        }
    }

    public function syncAdd()
    {
        $msg = "ADDRESS {$this->id} - Syncing ADDRESS...";
        print $msg . "\n";
        Log::info($msg);
        if(!$this->teams_civic_id)
        {
            $msg = "ADDRESS {$this->id} - TEAMSCIVIC does not exist...  Creating!...";
            print $msg . "\n";
            Log::info($msg);
            $civic = $this->createTeamsCivic();
            if(!$civic)
            {
                $msg = "ADDRESS {$this->id} - Failed to create TEAMS CIVIC!...";
                print $msg . "\n";
                Log::info($msg);
                throw new \Exception($msg);
            }
            $msg = "ADDRESS {$this->id} - Created TEAMS CIVIC with ID {$civic->civicAddressId}...";
            print $msg . "\n";
            Log::info($msg);
        } else {
            $msg = "ADDRESS {$this->id} - Found existing TEAMS CIVIC ID {$this->teams_civic_id}...";
            print $msg . "\n";
            Log::info($msg);
            return $this->teams_civic_id;
        }
    }

    public function compareTeamsCivic($civic = null)
    {
        if(!$civic)
        {
            $civic = $this->getTeamsCivic();
        }

        $matches = true;
        foreach($this->teamsAddressMapping as $addressKey => $teamsKey)
        {
            if($addressKey == "country")
            {
                if($this->iso3166ToAlpha2($this->$addressKey) != $this->iso3166ToAlpha2($civic->$teamsKey))
                {
                    print "No Address to Civic Match.".PHP_EOL; 

                    // Adding some additional print to screen for troubleshooting. TR 010622
                    //print_r($addressKey); 
                    //print_r($teamsKey); 
                    //print_r($this->iso3166ToAlpha2($this->$addressKey)); 
                    //print_r($this->iso3166ToAlpha2($civic->$teamsKey)); 
                    $matches = false;
                    break;
                }
            } else {
                //if($this->$addressKey != $civic->$teamsKey)
                //Added trim due to some whitespace.
                if(trim($this->$addressKey) != trim($civic->$teamsKey))
                {
                    print $this->$addressKey . " ==? " . $civic->$teamsKey . "\n";
                    print "No Country... No Key Match.".PHP_EOL; 
                                        
                    // Adding some additional print to screen for troubleshooting. TR 010622
                    print_r($this); 
                    print_r($civic); 
                    
                    print "AddressKey: ".$addressKey.PHP_EOL; 
                    print "TeamsKey: ".$teamsKey.PHP_EOL; 
                    var_dump(["Address" => $this->$addressKey, "Teams" => $civic->$teamsKey]); 
                    //var_dump($civic->$teamsKey);

                    
                    //die(); 
                    $matches = false;
                    break;
                }
            }
        }
        return $matches;
    }

    public function isValid()
    {
        $valid = true;
        $required = [
            'street_number',
            'street_name',
            'city',
            'state',
            'country',
            'latitude',
            'longitude',
        ];
        foreach($required as $element)
        {
            if(!$this->$element)
            {
                $valid = false;
                break;
            }
        }
        return $valid;
    }

    public function createTeamsCivic()
    {
        if(!$this->isValid())
        {
            throw new \Exception("ADDRESS is not valid, unable to create TEAMS CIVIC!");
        }
        $msg = "ADDRESS {$this->id} - createTeamsCivic()";
        print $msg . "\n";
        Log::info($msg);
        $civic = new TeamsCivic;
        $civic->companyName = $this->getSite()->name;
        $civic->description = $this->getSite()->name;
        foreach($this->teamsAddressMapping as $addressKey => $teamsKey)
        {
            $civic->$teamsKey = $this->$addressKey;
        }
        $civic->countryOrRegion = $this->iso3166ToAlpha2($this->country);
        $civicid = $civic->save();
        $msg = "ADDRESS {$this->id} - Created Civic ID: {$civicid}...";
        print $msg . "\n";
        Log::info($msg);
        if(!$civicid)
        {
            throw new \Exception("Failed to create TEAMS CIVIC!");
        }
        $civic->civicAddressId = $civicid;
        //$civic = TeamsCivic::find($civicid);
        //$status = $civic->validate();
        //if($status == false)
        //{
        //    throw new \Exception("Failed to validate TEAMS CIVIC!");
        //}
        $this->teams_civic_id = $civic->civicAddressId;
        $this->save();
        return $civic;
    }

    public static function iso3166ToAlpha2($countrycode)
    {
        $codes = [
            'USA'   =>  'US',
            'US'    =>  'US',
            'CAN'   =>  'CA',
            'CA'    =>  'CA',
            'MEX'   =>  'MX',
            'MX'    =>  'MX',
        ];
        foreach($codes as $old => $new)
        {
            if(strtoupper($countrycode) == $old)
            {
                return $new;
            }
        }
    }

    public static function iso3166ToAlpha3($countrycode)
    {
        $codes = [
            'US'    =>  'USA',
            'USA'   =>  'USA',
            'CA'    =>  'CAN',
            'CAN'   =>  'CAN',
            'MX'    =>  'MEX',
            'MEX'   =>  'MEX',
        ];
        foreach($codes as $old => $new)
        {
            if(strtoupper($countrycode) == $new)
            {
                return $old;
            }
        }
    }

}
