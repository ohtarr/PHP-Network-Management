<?php

namespace App\Models\Gizmo;

use App\Models\Gizmo\Gizmo;
use App\Models\Gizmo\TeamsCivic;
use App\Models\Gizmo\TeamsSwitch;
use App\Models\Location\Room;

class TeamsLocation extends Gizmo
{
    public static $key = "locationId";
    //public static $base_url = "";
    public static $all_url_suffix = "/api/e911/csonlinelislocations";
    public static $get_url_suffix = "/api/e911/csonlinelislocation";
    public static $find_url_suffix = "/api/e911/csonlinelislocation";
    public static $save_url_suffix = "/api/e911/csonlinelislocation/new";

    public $queryable = [ 
        "CivicAddressId",
        "City",
        "Location",
        "LocationId",
        "CountyOrRegion",
    ];

    public $saveable = [
        "Location",
    ];

    public function getTeamsCivic()
    {
        if($this->civicAddressId)
        {
            return TeamsCivic::find($this->civicAddressId);
        }
        print "No civicAddressId found!\n";
        return false;
    }

    public function getTeamsSwitches()
    {
        return TeamsSwitch::all()->where('locationId', $this->locationId);
    }

    public function getRoom()
    {
        return Room::where('teams_location_id',$this->locationId)->first();
    }

    public function cacheGetTeamsLocations($civicAddressId)
    {
        return $this->cacheAll()->where('civicAddressId', $civicAddressId);
    }

    public function cacheGetTeamsDefaultLocation($civicAddressId)
    {
        return $this->cacheAll()->where('civicAddressId',$civicAddressId)->whereNull('location')->first();
    }

    public function cacheGetTeamsNonDefaultLocations($civicAddressId)
    {
        return $this->cacheAll()->where('civicAddressId',$civicAddressId)->whereNotNull('location');
    }
}
TeamsLocation::init();