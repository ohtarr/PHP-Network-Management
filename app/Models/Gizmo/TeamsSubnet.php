<?php

namespace App\Models\Gizmo;

use App\Models\Gizmo\Gizmo;

class TeamsSubnet extends Gizmo
{
    //primary_Key of model.
    public static $key = "subnet";
    //url suffix to access ALL endpoint
    public static $all_url_suffix = "/api/e911/csonlinelissubnets";
    //url suffix to access GET endpoint
    public static $get_url_suffix = "/api/e911/csonlinelissubnet";
    //url suffix to access FIND endpoint
    public static $find_url_suffix = "/api/e911/csonlinelissubnet";
    //url suffix to access the SAVE endpoint
    public static $save_url_suffix = "/api/e911/csonlinelissubnet/new";

    //fields that are queryable by this model.
    public $queryable = [ 
        "subnet",
    ];

    //fields that are EDITABLE by this model.
    public $saveable = [
        "subnet",
        "description",
        "locationId",
    ];
}
//Initialize the model with the BASE_URL from env.
TeamsSubnet::init();