<?php

namespace App\Http\Controllers\Provisioning;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Sites;
use App\Models\ServiceNow\Location;
use App\Models\Gizmo\Dhcp;

class ProvisioningController extends Controller
{

    public function __construct()
    {
	    $this->middleware('auth:api');
    }

    public function getSnowLocations()
    {
        $locs = Location::where('companyISNOTEMPTY')->where('u_network_mob_dateISEMPTY')->where('u_network_demob_dateISEMPTY')->get();
        foreach($locs as $loc)
        {
            if($loc['name'])
            {
                $sitecodes[] = $loc['name'];
            }
        }
        sort($sitecodes);
        $return['status'] = 1;
        $return['count'] = count($sitecodes);
        $return['data'] = $sitecodes;
        return json_encode($return);
    }

    public function getNetboxSite($sitecode)
    {
        $site = Sites::where('name__ic', $sitecode)->first();
        return json_encode($site);
    }

    public function deployNetboxSite(Request $request, $sitecode)
    {

    }

    public function getDhcpScopes($sitecode)
    {
        $scope = Dhcp::getScopesBySitecode($sitecode);
        return json_encode($scope);
    }

    public function deployDhcpScopes(Request $request, $sitecode)
    {

    }

    public function getMistSite($sitecode)
    {

    }

    public function deployMistSite(Request $request, $sitecode)
    {

    }

    public function getNetboxDevices($sitecode)
    {

    }

    public function deployNetboxDevices(Request $request, $sitecode)
    {

    }

}
