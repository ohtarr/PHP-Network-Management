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
        $results = Assets::findByTag($serial);
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
        $return['status'] = 1;
        $submitted = $request->collect();
 /*        if(isset($submitted['location_id']))
        {
            $locationid = $submitted['location_id'];
            $this->addLog(1, "location_id {$locationid} submitted.");
        } else {
            $this->addLog(0, "location_id not found.");
            $return['status'] = 0;
        }
        if(isset($submitted['status_id']))
        {
            $statusid = $submitted['status_id'];
            $this->addLog(1, "status_id {$statusid} submitted.");
        } else {
            $this->addLog(0, "status_id not found.");
            $return['status'] = 0;
        }
        try{
            $location = Locations::find($locationid);
        } catch (\Exception $e) {
            $this->addLog(0, "Failed to FIND Location: " . $e->getMessage());
            $return['status'] = 0;
        }
        if(isset($location->id))
        {
            $this->addLog(1, "location {$location->name} found.");
        } else {
            $this->addLog(0, "location ID {$locationid} not found.");
            $return['status'] = 0;
        }
        try{
            $status = StatusLabels::find($statusid);
        } catch (\Exception $e) {
            $this->addLog(0, "Failed to FIND StatusLabel: " . $e->getMessage());
            $return['status'] = 0;
        }
        if(isset($status->id))
        {
            $this->addLog(1, "location {$status->name} found.");
        } else {
            $this->addLog(0, "location ID {$statusid} not found.");
            $return['status'] = 0;
        } */
        try{
            $asset = Assets::findByTag($serial);
        } catch (\Exception $e) {
            $this->addLog(0, "Failed to FIND Asset: " . $e->getMessage());
            $return['status'] = 0;
        }
        if(isset($asset->id))
        {
            $this->addLog(1, "Found asset ID {$asset->id} with serial {$serial}.");
        } else {
            $this->addLog(0, "Unable to find asset with serial {$serial}.");
            $return['status'] = 0;
        }
        try{
            $results = $asset->checkin($submitted);
            //$results = $asset->checkinCustom($locationid, $statusid);
        } catch (\Exception $e) {
            $this->addLog(0, $e->getMessage());
            $return['status'] = 0;
        }
        if(isset($results->id))
        {
            $this->addLog(1, "Asset ID {$results->id} checked in successfully");
        }
        $return['log'] = $this->logs;
        if(isset($results))
        {
            $return['data'] = $results;
        } else {
            $return['data'] = null;
        }
        return json_encode($return);
    }

    public function checkoutAsset($serial, Request $request)
    {
        $return['status'] = 1;
        $submitted = $request->collect();
        try{
            $asset = Assets::findByTag($serial);
        } catch (\Exception $e) {
            $this->addLog(0, "Failed to FIND Asset: " . $e->getMessage());
            $return['status'] = 0;
        }
        if(isset($asset->id))
        {
            $this->addLog(1, "Found asset ID {$asset->id} with serial {$serial}.");
        } else {
            $this->addLog(0, "Unable to find asset with serial {$serial}.");
            $return['status'] = 0;
        }
        try{
            $results = $asset->checkout($submitted);
        } catch (\Exception $e) {
            $this->addLog(0, $e->getMessage());
            $return['status'] = 0;
        }
        if(isset($results->id))
        {
            $this->addLog(1, "Asset ID {$asset->id} successfully checked out.");
        }
        $return['log'] = $this->logs;
        if(isset($results->id))
        {
            $return['data'] = $results;
        } else {
            $return['data'] = null;
        }
        return json_encode($return);
    }

    public function updateAsset($serial, Request $request)
    {
        $return['status'] = 1;
        $submitted = $request->collect();
        try{
            $asset = Assets::findByTag($serial);
        } catch (\Exception $e) {
            $this->addLog(0, "Failed to GET asset: " . $e->getMessage());
        }
        if(isset($asset->id))
        {
            $this->addLog(1, "Found asset ID {$asset->id} with serial {$serial}.");
            try{
                $results = $asset->update($submitted);
            } catch (\Exception $e) {
                $this->addLog(0, "Failed to UPDATE asset: " . $e->getMessage());
            }
            if(isset($results->id))
            {
                $this->addLog(1, "Successfully updated Asset ID {$results->id}");
            }
        } else {
            $this->addLog(0, "Unable to find asset with serial {$serial}.");
            $return['status'] = 0;
        }
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    public function createAsset(Request $request)
    {
        $submitted = $request->collect();
        try{
            $results = Assets::create($submitted);
        } catch (\Exception $e) {
            $this->addLog(0, "Failed to create Asset:" . $e->getMessage());
        }
        $this->addLog(1, "Created Asset ID: {$results->id}");
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }
}