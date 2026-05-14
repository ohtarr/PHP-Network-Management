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
        return json_encode($return);
    }

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
        return $return;
    }

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
        return $return;
    }

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
        return $return;
    }

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

        $scopes = $netboxsite->getDhcpScopesByPrefixes();
        $this->addLog(1, "Found " . count($scopes) . " Scopes to delete.");

        foreach($scopes as $scope)
        {
            $scopeids[] = (object) ['subnet' => $scope->subnet];
        }

        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = $scopeids;
        return $return;
    }

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


/*             $deletescope2 = Dhcp::findScopeByIp($prefix->network());
            if(isset($deletescope2->scopeID))
            {
                $this->addLog(1, "SCOPE {$deletescope2->scopeID} found in GIZMO and ready to delete.");
                try{
                    $deletescope2->delete();
                } catch (\Exception $e) {
                    $this->addLog(0, "Received error from GIZMO: " . $e->getMessage());
                }
                $checkscope2 = Dhcp::findScopeByIp($prefix->network());
                if(isset($checkscope2->scopeID))
                {
                    $this->addLog(0, "SCOPE {$prefix->network()} did not delete correctly in GIZMO!");
                    $totalstatus = 0;
                } else {
                    $this->addLog(1, "SCOPE {$prefix->network()} deleted successfully in GIZMO.");
                }
            } else {
                $this->addLog(1, "SCOPE {$prefix->network()} not found in GIZMO.");
            } */
        }
        $return['status'] = $totalstatus;
        $return['log'] = $this->logs;
        $return['data'] = null;
        return $return;
    }

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

        $scopes = $netboxsite->getDhcpScopes();
        $scopecount = $scopes->count();
        if($scopecount > 0)
        {
            $this->addLog(0, "Found {$scopecount} scopes for site {$netboxsite->name}, cancelling netbox site deletion.");
            $return['status'] = 0;
            $return['log'] = $this->logs;
            $return['data'] = null;
            return $return;
        } else {
            $this->addLog(1, "Found {$scopecount} scopes for site {$netboxsite->name}.");
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
        return $return;
    }

}
