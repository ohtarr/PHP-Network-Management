<?php

namespace App\Http\Controllers\Deprovisioning;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\DCIM\Locations;
use App\Models\Netbox\IPAM\AsnRanges;
use App\Models\Netbox\IPAM\Asns;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\DCIM\VirtualChassis;
use App\Models\ServiceNow\Location;
use App\Models\Mist\Site;
use App\Models\Mist\Device;
use App\Models\Gizmo\Dhcp;
use \Carbon\Carbon;
use App\Models\Log\Log as DbLog;
use App\Models\Dhcp\SubnetV4;

class DeprovisioningController extends Controller
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
     *     path="/deprovisioning/snowlocations/{days}",
     *     summary="Get ServiceNow locations recently decommissioned",
     *     description="Returns site codes that have a network mob date set and a demob date within the last N days, and also exist as a Netbox site.",
     *     tags={"Deprovisioning"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="days",
     *         in="path",
     *         required=false,
     *         description="Number of days to look back for decommissioned sites (default: 90)",
     *         @OA\Schema(type="integer", example=90)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of site codes eligible for deprovisioning",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="array", @OA\Items(type="string", example="KHONELAB"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getSnowLocations($days = 90)
    {
        $netboxsites = Sites::all();
        $totalstatus = 1;
        $locs = Location::where('companyISNOTEMPTY')->where('u_network_mob_dateISNOTEMPTY')->where('u_network_demob_date', '>=', Carbon::now()->subDays($days)->toDateString())->get();
        if(!$locs)
        {
            $this->addLog(0, "Unable to find valid SNOW location.");
            $totalstatus = 0;

            $return['status'] = $totalstatus;
            $return['log'] = $this->logs;
            return json_encode($return);
        }
        foreach($locs as $loc)
        {
            unset($netboxsite);
            if($loc['name'])
            {
                $netboxsite = $netboxsites->where('name', $loc['name'])->first();
                if(isset($netboxsite->name))
                {
                    $sitecodes[] = $loc['name'];
                }
            }
        }
        sort($sitecodes);
        $count = count($sitecodes);
        $this->addLog(1, $count . " SNOW LOCATIONS successfully retreived.");
        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = $sitecodes;
        return response()->json($return);
    }

    /**
     * @OA\Delete(
     *     path="/deprovisioning/mist/site/{sitecode}/devices",
     *     summary="Unassign all Mist devices from a site",
     *     description="Finds all devices assigned to the Mist site and unassigns them.",
     *     tags={"Deprovisioning"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="sitecode",
     *         in="path",
     *         required=true,
     *         description="The site code whose Mist devices should be unassigned",
     *         @OA\Schema(type="string", example="KHONELAB")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Unassignment result",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function unassignMistDevices($sitecode)
    {
        $user = auth()->user();
		if ($user->cant('provision-mist-devices')) {
			abort(401, 'You are not authorized');
        }

        $totalstatus = 1;

        $mistsite = Site::findByName($sitecode);
        if(!isset($mistsite->id))
        {
            $this->addLog(0, "Unable to find MIST SITE {$sitecode}.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "Found MIST SITE ID: {$mistsite->id}.");
        }

        $mistdevices = $mistsite->getDevices();
        $this->addLog(1, "Found " . $mistdevices->count() . " devices for MIST SITE ID: {$mistsite->id}.");
        foreach($mistdevices as $mistdevice)
        {
            $this->addLog(1, "UNASSIGNING device {$mistdevice->name} from MIST SITE ID: {$mistsite->id}.");
            $mistdevice->unassign();
        }

        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = null;
        return response()->json($return);
    }

    /**
     * @OA\Delete(
     *     path="/deprovisioning/mist/site/{sitecode}",
     *     summary="Delete a Mist site",
     *     description="Deletes the Mist site for the given site code. Will fail if devices are still assigned to the site.",
     *     tags={"Deprovisioning"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="sitecode",
     *         in="path",
     *         required=true,
     *         description="The site code of the Mist site to delete",
     *         @OA\Schema(type="string", example="KHONELAB")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deletion result",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function deleteMistSite($sitecode)
    {
        $user = auth()->user();
		if ($user->cant('provision-mist-devices')) {
			abort(401, 'You are not authorized');
        }

        $totalstatus = 1;

        $mistsite = Site::findByName($sitecode);
        if(!isset($mistsite->id))
        {
            $this->addLog(0, "Unable to find MIST SITE {$sitecode}.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "Found MIST SITE ID: {$mistsite->id}.");
        }

        $mistdevices = $mistsite->getDevices();
        $count = $mistdevices->count();
        $this->addLog(1, "Found {$count} devices for MIST SITE ID: {$mistsite->id}.");
        if($count > 0)
        {
            $this->addLog(0, "MIST SITE ID: {$mistsite->id} has devices assigned to it, unable to delete!");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        }
        $mistsite->delete();

        $confirm = Site::findByName($sitecode);
        if(!isset($confirm->id))
        {
            $this->addLog(1, "MIST SITE {$sitecode} has been deleted.");
        } else {
            $this->addLog(0, "MIST SITE {$sitecode} FAILED to delete!");
            $totalstatus = 0;
        }

        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = null;
        return response()->json($return);
    }

    /**
     * @OA\Delete(
     *     path="/deprovisioning/dhcp/scope/{scope}",
     *     summary="Delete a single DHCP scope by network address",
     *     description="Deletes the specified DHCP scope from both KEA and Gizmo DHCP systems.",
     *     tags={"Deprovisioning"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="scope",
     *         in="path",
     *         required=true,
     *         description="The network address of the DHCP scope to delete (e.g. 10.1.1.0)",
     *         @OA\Schema(type="string", example="10.1.1.0")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deletion result",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function deleteDhcpScope($scope)
    {
        $user = auth()->user();
		if ($user->cant('provision-mist-devices')) {
			abort(401, 'You are not authorized');
        }

        $totalstatus = 1;

        $deletescope = SubnetV4::findByIp($scope);
        if(isset($deletescope->subnet))
        {
            $this->addLog(1, "SCOPE {$deletescope->subnet} found in KEA.");
            $deletescope->delete();
            $checkscope = SubnetV4::findByIp($scope);
            if(isset($checkscope->subnet))
            {
                $this->addLog(0, "SCOPE {$scope} did not delete correctly in KEA!");
                $totalstatus = 0;
            } else {
                $this->addLog(1, "SCOPE {$scope} deleted successfully in KEA.");
            }
        } else {
            $this->addLog(1, "SCOPE {$scope} not found in KEA.");
        }


        $deletescope2 = Dhcp::findScopeByIp($scope);
        if(isset($deletescope2->scopeID))
        {
            $this->addLog(1, "SCOPE {$deletescope2->scopeID} found in GIZMO and ready to delete.");
            $deletescope2->delete();
            $checkscope2 = Dhcp::findScopeByIp($scope);
            if(isset($checkscope2->scopeID))
            {
                $this->addLog(0, "SCOPE {$scope} did not delete correctly in GIZMO!");
                $totalstatus = 0;
            } else {
                $this->addLog(1, "SCOPE {$scope} deleted successfully in GIZMO.");
            }
        } else {
            $this->addLog(1, "SCOPE {$scope} not found in GIZMO.");
        }
        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = null;
        return response()->json($return);
    }

    /**
     * @OA\Get(
     *     path="/deprovisioning/dhcp/{sidecode}/todelete",
     *     summary="Get Kea DHCP scopes that would be deleted for a site",
     *     description="Returns the list of Kea DHCP scopes associated with the site's prefixes without actually deleting them.",
     *     tags={"Deprovisioning"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="sidecode",
     *         in="path",
     *         required=true,
     *         description="The site code to preview DHCP scope deletions for",
     *         @OA\Schema(type="string", example="KHONELAB")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of Kea DHCP scopes that would be deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="subnet", type="string", example="10.1.1.0")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function dhcpScopesToDelete($sitecode)
    {
        $user = auth()->user();
		if ($user->cant('provision-mist-devices')) {
			abort(401, 'You are not authorized');
        }

        $totalstatus = 1;

        $netboxsite = Sites::where('name__ic',$sitecode)->first();
        if(!isset($netboxsite->id))
        {
            $this->addLog(0, "SITE {$sitecode} not found.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "SITE ID {$netboxsite->id} found.");
        }

        $scopes = $netboxsite->getKeaDhcpScopesBySupernets();
        $this->addLog(1, "Found " . count($scopes) . " Scopes to delete.");

        foreach($scopes as $scope)
        {
            $scopeids[] = (object) ['subnet' => $scope->subnet];
        }

        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = $scopeids;
        return response()->json($return);
    }

    /**
     * @OA\Get(
     *     path="/deprovisioning/dhcp/{sidecode}/todelete/gizmo",
     *     summary="Get Gizmo DHCP scopes that would be deleted for a site",
     *     description="Returns the list of Gizmo DHCP scopes associated with the site's prefixes without actually deleting them.",
     *     tags={"Deprovisioning"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="sidecode",
     *         in="path",
     *         required=true,
     *         description="The site code to preview DHCP scope deletions for",
     *         @OA\Schema(type="string", example="KHONELAB")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of Gizmo DHCP scopes that would be deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="subnet", type="string", example="10.1.1.0")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function dhcpScopesToDeleteGizmo($sitecode)
    {
        $user = auth()->user();
		if ($user->cant('provision-mist-devices')) {
			abort(401, 'You are not authorized');
        }

        $totalstatus = 1;

        $netboxsite = Sites::where('name__ic',$sitecode)->first();
        if(!isset($netboxsite->id))
        {
            $this->addLog(0, "SITE {$sitecode} not found.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "SITE ID {$netboxsite->id} found.");
        }

        $scopes = $netboxsite->getGizmoDhcpScopesBySupernets();
        $this->addLog(1, "Found " . count($scopes) . " Scopes to delete.");

        foreach($scopes as $scope)
        {
            $scopeids[] = (object) ['subnet' => $scope->subnet];
        }

        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = $scopeids;
        return response()->json($return);
    }

    /**
     * @OA\Delete(
     *     path="/deprovisioning/dhcp/{sitecode}",
     *     summary="Delete all DHCP scopes for a site",
     *     description="Deletes all active DHCP scopes associated with the site's Netbox prefixes from KEA DHCP.",
     *     tags={"Deprovisioning"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="sitecode",
     *         in="path",
     *         required=true,
     *         description="The site code whose DHCP scopes should be deleted",
     *         @OA\Schema(type="string", example="KHONELAB")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deletion result",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function deleteSiteDhcpScopes($sitecode)
    {
        $user = auth()->user();
		if ($user->cant('provision-mist-devices')) {
			abort(401, 'You are not authorized');
        }

        $totalstatus = 1;

        $netboxsite = Sites::where('name__ic',$sitecode)->first();
        if(!isset($netboxsite->id))
        {
            $this->addLog(0, "SITE {$sitecode} not found.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "SITE ID {$netboxsite->id} found.");
        }

        $prefixes = $netboxsite->getActivePrefixes();

        $count = $prefixes->count();
        $this->addLog(1, "Found {$count} Active Prefixes for site {$netboxsite->name}.");
        
        foreach($prefixes as $prefix)
        {
            $deletescope = SubnetV4::findByIp($prefix->network());
            if(isset($deletescope->subnet))
            {
                $this->addLog(1, "SCOPE {$deletescope->subnet} found in KEA.");
                try{
                    $deletescope->delete();
                } catch (\Exception $e) {
                    $this->addLog(0, "Received error from KEA-DHCP: " . $e->getMessage());
                }
                $checkscope = SubnetV4::findByIp($prefix->network());
                if(isset($checkscope->subnet))
                {
                    $this->addLog(0, "SCOPE {$prefix->network()} did not delete correctly in KEA!");
                    $totalstatus = 0;
                } else {
                    $this->addLog(1, "SCOPE {$prefix->network()} deleted successfully in KEA.");
                }
            } else {
                $this->addLog(1, "SCOPE {$prefix->network()} not found in KEA.");
            }
        }
        $checkscopes = $netboxsite->getKeaDhcpScopesBySupernets();
        if($checkscopes->count() > 0)
        {
            $totalstatus = 0;
            foreach($checkscopes as $checkscope)
            {
                $this->addLog(0, "CHILD SCOPE {$checkscope->subnet} still exists, something is wrong!");
            }
        } else {
                $this->addLog(1, "no CHILD SCOPES remain in KEA.  DHCP scopes have been deprovisioned.");
        }
        $gizmoscopes = $netboxsite->getGizmoDhcpScopesBySupernets();
        if($gizmoscopes->count() > 0)
        {
            $totalstatus = 0;
            foreach($gizmoscopes as $gizmoscope)
            {
                $this->addLog(0, "CHILD SCOPE {$gizmoscope->scopeID} exists in Gizmo.  Submit ticket to delete.");
            }
        } else {
                $this->addLog(1, "no CHILD SCOPES exist in GIZMO.");
        }
        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = null;
        return response()->json($return);
    }

    /**
     * @OA\Delete(
     *     path="/deprovisioning/netbox/site/{sitecode}",
     *     summary="Delete a Netbox site and all associated resources",
     *     description="Fully deprovisions a Netbox site by deleting all devices (and their virtual chassis), IP addresses, IP ranges, active prefixes, ASNs, locations, and the site itself. Will refuse if DHCP scopes or a Mist site still exist.",
     *     tags={"Deprovisioning"},
     *     security={{"oauth2":{"openid","profile","email","api://915c46fe-ee91-41c7-98ab-b257b04ea7ec/access_as_user"}}},
     *     @OA\Parameter(
     *         name="sitecode",
     *         in="path",
     *         required=true,
     *         description="The site code of the Netbox site to delete",
     *         @OA\Schema(type="string", example="KHONELAB")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deletion result",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="log", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="data", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function deleteNetboxSite($sitecode)
    {
        $user = auth()->user();
		if ($user->cant('provision-mist-devices')) {
			abort(401, 'You are not authorized');
        }

        $totalstatus = 1;

        $netboxsite = Sites::where('name__ic',$sitecode)->first();
        if(!isset($netboxsite->id))
        {
            $this->addLog(0, "SITE {$sitecode} not found.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "SITE ID {$netboxsite->id} found.");
        }

        $scopes = $netboxsite->getKeaDhcpScopesBySupernets();
        $scopecount = $scopes->count();
        if($scopecount > 0)
        {
            $this->addLog(0, "Found {$scopecount} KEA scopes for site {$netboxsite->name}, cancelling netbox site deletion.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "Found {$scopecount} KEA scopes for site {$netboxsite->name}.");
        }

        $scopes = $netboxsite->getGizmoDhcpScopesBySupernets();
        $scopecount = $scopes->count();
        if($scopecount > 0)
        {
            $this->addLog(0, "Found {$scopecount} GIZMO scopes for site {$netboxsite->name}, cancelling netbox site deletion.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "Found {$scopecount} GIZMO scopes for site {$netboxsite->name}.");
        }

        $mistsite = $netboxsite->getMistSite();
        if(!isset($mistsite->id))
        {
            $this->addLog(1, "MIST SITE {$sitecode} does NOT exist.");

        } else {
            $this->addLog(0, "Found MIST SITE ID: {$mistsite->id}, cancelling netbox site deletion.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        }

        $netboxdevices = $netboxsite->devices();
        foreach($netboxdevices as $device)
        {
            $freshdevice = Devices::find($device->id);
            $vc = $freshdevice->getVirtualChassis();
            if(isset($vc->id))
            {
                $vc->delete();
                $this->addLog(1, "Deleted VirtualChassis {$vc->name}.");
            }
            $device->delete();
            $freshdevice = Devices::where('id',$device->id)->get()->first();
            if(!isset($freshdevice->id))
            {
                $this->addLog(1, "Successfully deleted device {$device->name}.");
            }
        }

        $activeprefixes = $netboxsite->getActivePrefixes();
        foreach($activeprefixes as $activeprefix)
        {
            $ips = $activeprefix->getIpAddresses();
            foreach($ips as $ip)
            {
                $ip->delete();
                $this->addLog(1, "Deleted IP ADDRESS {$ip->address}.");
            }
            $ranges = $activeprefix->getIpRanges();
            foreach($ranges as $range)
            {
                $range->delete();
                $this->addLog(1, "Deleted IP RANGE {$range->display}.");
            }
            $activeprefix->delete();
            $this->addLog(1, "Deleted PREFIX {$activeprefix->prefix}.");
        }

        $availparams = [
            'status'        =>  "available",
            'scope_type'    =>  null,
            'scope_id'      =>  null,
            'description'   =>  "",
        ];
        $supernets = $netboxsite->getSupernets();
        foreach($supernets as $supernet)
        {
            $supernet->update($availparams);
            $this->addLog(1, "Set PREFIX {$supernet->prefix} back to AVAILABLE.");
        }

        $asns = $netboxsite->getAsns();
        foreach($asns as $asn)
        {
            $asn->delete();
            $this->addLog(1, "Deleted ASN {$asn->asn}.");
        }

        $locations = $netboxsite->locations();
        foreach($locations as $location)
        {
            $location->delete();
            $this->addLog(1, "Deleted LOCATION {$location->name}.");
        }

        $netboxsite->delete();
        $this->addLog(1, "Deleted SITE {$netboxsite->name}.");

        $return['status'] = 1;
        $return['log'] = $this->logs;
        $return['data'] = null;
        return response()->json($return);
    }

}
