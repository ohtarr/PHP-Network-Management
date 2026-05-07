<?php

namespace App\Http\Controllers\SnipeIT;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Log\Log as DbLog;
use App\Models\Mist\Site;
use App\Models\Mist\Device;
use App\Models\SnipeIT\Locations;
use App\Models\SnipeIT\Models;
use App\Models\SnipeIT\StatusLabels;
use App\Models\SnipeIT\Categories;
use App\Models\SnipeIT\Assets;
use \Carbon\Carbon;

class SnipeITController extends Controller
{
    public $logs = [];

    public function __construct()
    {
	    $this->middleware('auth:api');
    }

    public function addLog($status, $msg)
    {
        $username = auth()->user()?->userPrincipalName;
        $class = class_basename($this);
        $msg1 = $class . ": " . debug_backtrace()[1]['function'] . ": " . $msg;
        $this->logs[] = [
            'status' => $status,
            'msg'    => $msg,
        ];

        DbLog::log($msg1, $username, 'provisioning');
    }

    public function getAssets()
    {
        $results = Assets::all();
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    public function getAssetsBySerial($serial)
    {
        $results = Assets::getBySerial($serial);
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    public function getLocations()
    {
        $results = Locations::all();
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    public function getCategories()
    {
        $results = Categories::all();
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    public function getModels()
    {
        $results = Models::all();
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    public function getStatusLabels()
    {
        $results = StatusLabels::all();
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    public function checkinAsset($serial, Request $request)
    {
        $submitted = $request->collect();
        if(isset($submitted['location_id']))
        {
            $locationid = $submitted['location_id'];
            $this->addLog(1, "location_id {$locationid} submitted.");
        } else {
            $this->addLog(0, "location_id not found.");
        }
        if(isset($submitted['status_id']))
        {
            $statusid = $submitted['status_id'];
            $this->addLog(1, "status_id {$statusid} submitted.");
        } else {
            $this->addLog(0, "status_id not found.");
        }
        $location = Locations::find($locationid);
        if(isset($location->id))
        {
            $this->addLog(1, "location {$location->name} found.");
        } else {
            $this->addLog(0, "location ID {$locationid} not found.");
        }
        $status = StatusLabels::find($statusid);
        if(isset($status->id))
        {
            $this->addLog(1, "location {$status->name} found.");
        } else {
            $this->addLog(0, "location ID {$statusid} not found.");
        }
        $asset = Assets::getBySerial($serial);
        if(isset($asset->id))
        {
            $this->addLog(1, "Found asset ID {$asset->id} with serial {$serial}.");
        } else {
            $this->addLog(0, "Unable to find asset with serial {$serial}.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return json_encode($return);
        }
        $results = $asset->checkinCustom($locationid, $statusid);
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    public function checkoutAssetToLocation($serial, Request $request)
    {
        $submitted = $request->collect();
        if(isset($submitted['location_id']))
        {
            $locationid = $submitted['location_id'];
            $this->addLog(1, "location_id {$locationid} submitted.");
        } else {
            $this->addLog(0, "location_id not found.");
        }
        $location = Locations::find($locationid);
        if(isset($location->id))
        {
            $this->addLog(1, "location {$location->name} found.");
        } else {
            $this->addLog(0, "location ID {$locationid} not found.");
        }
        $asset = Assets::getBySerial($serial);
        if(isset($asset->id))
        {
            $this->addLog(1, "Found asset ID {$asset->id} with serial {$serial}.");
        } else {
            $this->addLog(0, "Unable to find asset with serial {$serial}.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return json_encode($return);
        }
        $results = $asset->checkoutToLocationId($locationid);
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    public function updateAsset($serial, Request $request)
    {
        $submitted = $request->collect();
        $asset = Assets::getBySerial($serial);
        if(isset($asset->id))
        {
            $this->addLog(1, "Found asset ID {$asset->id} with serial {$serial}.");
        } else {
            $this->addLog(0, "Unable to find asset with serial {$serial}.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return json_encode($return);
        }
        $results = $asset->update($submitted);
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    public function createAsset(Request $request)
    {
        $submitted = $request->collect();
        $results = Assets::create($submitted);
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }
}