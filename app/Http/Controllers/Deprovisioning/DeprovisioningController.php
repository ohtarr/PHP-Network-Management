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
use Illuminate\Support\Facades\Log;

class DeprovisioningController extends Controller
{
    public $logs = [];

    public function __construct()
    {
	    $this->middleware('auth:api');
    }

    public function addLog($status, $msg)
    {
        $this->logs[] = [
            'status'    =>  $status,
            'msg'       =>  $msg,
        ];
        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . debug_backtrace()[1]['function'] . ": " . $msg);
    }

    public function unassignMistDevices($sitecode)
    {
        $user = auth()->user();
		if ($user->cant('provision-mist-devices')) {
			abort(401, 'You are not authorized');
        }

        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__);
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

        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__);
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

    public function deleteDhcpScopes($sitecode)
    {
        $user = auth()->user();
		if ($user->cant('provision-mist-devices')) {
			abort(401, 'You are not authorized');
        }

        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__);
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
        $count = $scopes->count();
        $this->addLog(1, "Found {$count} scopes for site {$netboxsite->name}.");
        
        foreach($scopes as $scope)
        {
            $scope->delete();
            $confirm = Dhcp::find($scope->scopeID);
            if(isset($confim->scopeID))
            {
                $this->addLog(0, "FAILED to delete DHCP SCOPE {$scope->scopeID} for site {$netboxsite->name}...");
                $totalstatus = 0;
            } else {
                $this->addLog(1, "Deleted DHCP SCOPE {$scope->scopeID} for site {$netboxsite->name}...");
            }
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

        Log::channel('provisioning')->info(auth()->user()->userPrincipalName . " : " . __FUNCTION__);
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
