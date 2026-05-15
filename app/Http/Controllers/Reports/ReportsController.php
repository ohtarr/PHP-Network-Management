<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\IPAM\Roles;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Gizmo\Dhcp;
use App\Models\LibreNMS\Device;

class ReportsController extends Controller
{
    public function __construct()
    {
	    //$this->middleware('auth:api');
    }

    /**
     * @OA\Get(
     *     path="/reports/sitesubnets",
     *     summary="Get a report of all site supernet subnets",
     *     description="Returns a map of site names to their associated supernet prefixes from Netbox.",
     *     tags={"Reports"},
     *     @OA\Response(
     *         response=200,
     *         description="Site subnet report keyed by site name",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function siteSubnetReport()
    {
        $role = Roles::where('name', 'SITE_SUPERNET')->first();
        $prefixes = Prefixes::where('role_id', $role->id)->get();
        foreach($prefixes as $prefix)
        {
            if(isset($prefix->scope->name))
            {
                unset($tmp);
                $tmp['network'] = $prefix->network();
                $tmp['bitmask'] = $prefix->length();
                $sitesubnets[$prefix->scope->name]['networks'][] = $tmp;
            }
        }
        ksort($sitesubnets);
        return response()->json($sitesubnets);
    }

    /**
     * @OA\Get(
     *     path="/reports/dhcp/orphanedscopes",
     *     summary="Get DHCP scopes that are not associated with any known Netbox site",
     *     description="Compares all DHCP scopes against Netbox site supernets and returns scopes that do not fall within any known site supernet.",
     *     tags={"Reports"},
     *     @OA\Response(
     *         response=200,
     *         description="List of orphaned DHCP scopes",
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     )
     * )
     */
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
        return response()->json($scopes);
    }

    /**
     * @OA\Get(
     *     path="/reports/opengear/status",
     *     summary="Get ICMP and SNMP status of all Opengear OOB devices",
     *     description="Returns a combined status report for Opengear devices, including ICMP ping status and SNMP polling status from LibreNMS.",
     *     tags={"Reports"},
     *     @OA\Response(
     *         response=200,
     *         description="Opengear device status list sorted by site name",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="name", type="string", example="SITE01"),
     *                 @OA\Property(property="icmp", type="object", description="LibreNMS ICMP device record"),
     *                 @OA\Property(property="snmp", type="object", description="LibreNMS SNMP device record")
     *             )
     *         )
     *     )
     * )
     */
    public function getOpengearStatus()
    {
        $final = [];
        $icmp = Device::get(['type'=>'os', 'query'=>'ping']);
        $snmp = Device::get(['type'=>'os', 'query'=>'opengear']);
        $namereg = "/(\S+)-oob/i";
        foreach($icmp as $device)
        {
            if(preg_match($namereg, $device->hostname, $hits))
            {
                $final[strtoupper($hits[1])]['icmp'] = $device;
            }
        }
        foreach($snmp as $device)
        {
            $final[strtoupper($device->hostname)]['snmp'] = $device;
        }
        ksort($final);
        foreach($final as $name => $object)
        {
            unset($tmp);
            $tmp = $object;
            $tmp['name'] = $name;
            $newarray[] = $tmp;
        }
        return response()->json($newarray);
    }
}
