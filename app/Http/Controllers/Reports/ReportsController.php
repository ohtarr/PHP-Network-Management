<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\IPAM\Roles;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Gizmo\Dhcp;

class ReportsController extends Controller
{
    public function __construct()
    {
	    //$this->middleware('auth:api');
    }

    public function siteSubnetReport()
    {
        $role = Roles::where('name', 'SITE_SUPERNET')->first();
        $prefixes = Prefixes::where('role_id', $role->id)->get();
        foreach($prefixes as $prefix)
        {
            if(isset($prefix->scope->name))
            {
                unset($cidr);
                unset($tmp);
                $cidr = $prefix->cidr();
                $tmp['network'] = $cidr['network'];
                $tmp['bitmask'] = $cidr['bitmask'];
                $sitesubnets[$prefix->scope->name]['networks'][] = $tmp;
            }
        }
        ksort($sitesubnets);
        return json_encode($sitesubnets);
    }

    public function getOrphanedDhcpScopes()
    {
        $scopes = Dhcp::all();
        $netboxsites = Sites::all();
        foreach($netboxsites as $netboxsite)
        {
            $supernets = $netboxsite->getSupernets();

            foreach($supernets as $supernet)
            {
                $supernetscopes = $scopes->findOverlap($supernet->network(), $supernet->length());
                foreach($supernetscopes as $supernetscope)
                {
                    $scopes = $scopes->removeScope($supernetscope->scopeID);
                }
            }
        }
        return $scopes;
    }
}
