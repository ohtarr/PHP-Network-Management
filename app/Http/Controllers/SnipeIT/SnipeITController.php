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

    /**
     * @OA\Get(
     *     path="/snipeit/hardware",
     *     summary="Get all SnipeIT hardware assets",
     *     tags={"SnipeIT"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all hardware assets",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getAssets()
    {
        $results = Assets::all();
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    /**
     * @OA\Get(
     *     path="/snipeit/hardware/byserial/{serial}",
     *     summary="Get a SnipeIT hardware asset by serial number",
     *     tags={"SnipeIT"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="serial",
     *         in="path",
     *         required=true,
     *         description="The serial number (asset tag) to look up",
     *         @OA\Schema(type="string", example="AB1234567890")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Asset matching the serial number",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getAssetsBySerial($serial)
    {
        $results = Assets::findByTag($serial);
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    /**
     * @OA\Get(
     *     path="/snipeit/locations",
     *     summary="Get all SnipeIT locations",
     *     tags={"SnipeIT"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all SnipeIT locations",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getLocations()
    {
        $results = Locations::all();
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    /**
     * @OA\Get(
     *     path="/snipeit/categories",
     *     summary="Get all SnipeIT asset categories",
     *     tags={"SnipeIT"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all SnipeIT categories",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getCategories()
    {
        $results = Categories::all();
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    /**
     * @OA\Get(
     *     path="/snipeit/models",
     *     summary="Get all SnipeIT asset models",
     *     tags={"SnipeIT"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all SnipeIT models",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getModels()
    {
        $results = Models::all();
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    /**
     * @OA\Get(
     *     path="/snipeit/statuslabels",
     *     summary="Get all SnipeIT status labels",
     *     tags={"SnipeIT"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of all SnipeIT status labels",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getStatusLabels()
    {
        $results = StatusLabels::all();
        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = $results;
        return json_encode($return);
    }

    /**
     * @OA\Post(
     *     path="/snipeit/hardware/{serial}/checkin",
     *     summary="Check in a SnipeIT hardware asset by serial number",
     *     tags={"SnipeIT"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="serial",
     *         in="path",
     *         required=true,
     *         description="The serial number (asset tag) of the asset to check in",
     *         @OA\Schema(type="string", example="AB1234567890")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", description="Optional check-in parameters (e.g. location_id, status_id)")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Check-in result",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function checkinAsset($serial, Request $request)
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
            $results = $asset->checkin($submitted);
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

    /**
     * @OA\Post(
     *     path="/snipeit/hardware/{serial}/checkout",
     *     summary="Check out a SnipeIT hardware asset by serial number",
     *     tags={"SnipeIT"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="serial",
     *         in="path",
     *         required=true,
     *         description="The serial number (asset tag) of the asset to check out",
     *         @OA\Schema(type="string", example="AB1234567890")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(type="object", description="Optional checkout parameters (e.g. assigned_to, checkout_to_type)")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checkout result",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/snipeit/hardware/{serial}",
     *     summary="Update a SnipeIT hardware asset by serial number",
     *     tags={"SnipeIT"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="serial",
     *         in="path",
     *         required=true,
     *         description="The serial number (asset tag) of the asset to update",
     *         @OA\Schema(type="string", example="AB1234567890")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", description="Fields to update on the asset")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Update result",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/snipeit/hardware",
     *     summary="Create a new SnipeIT hardware asset",
     *     tags={"SnipeIT"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             description="Asset fields to create",
     *             @OA\Property(property="asset_tag", type="string", example="AB1234567890"),
     *             @OA\Property(property="model_id", type="integer", example=5),
     *             @OA\Property(property="status_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="SITE01-SW-1")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Created asset",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
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
