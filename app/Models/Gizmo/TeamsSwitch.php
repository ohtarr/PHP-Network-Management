<?php

namespace App\Models\Gizmo;

use App\Models\Gizmo\Gizmo;
use App\Models\Gizmo\TeamsLocation;

class TeamsSwitch extends Gizmo
{
    //primary_Key of model.
    public static $key = "chassisId";
    //url suffix to access ALL endpoint
    public static $all_url_suffix = "/api/e911/csonlinelisswitches";
    //url suffix to access GET endpoint
    public static $get_url_suffix = "/api/e911/csonlinelisswitch";
    //url suffix to access FIND endpoint
    public static $find_url_suffix = "/api/e911/csonlinelisswitch";
    //url suffix to access the SAVE endpoint
    public static $save_url_suffix = "/api/e911/csonlinelisswitch/new";

    //fields that are queryable by this model.
    public $queryable = [ 
        "chassisId",
    ];

    //fields that are EDITABLE by this model.
    public $saveable = [
        "chassisId",
        "Description",
        "LocationId",
    ];

    public function getTeamsLocation()
    {
        if($this->locationId)
        {
            return TeamsLocation::find($this->locationId);
        }
        print "No locationId found!\n";
        return false;
    }
}
//Initialize the model with the BASE_URL from env.
TeamsSwitch::init();