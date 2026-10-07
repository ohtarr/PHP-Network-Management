<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Sites;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Mist\Site;
use App\Models\Gizmo\Dhcp;
use App\Models\Dhcp\SubnetV4;

class AuditSiteDhcpHelpers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:AuditSiteDhcpHelpers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit Site DHCP settings';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $nosupernets = [];
        $noscopes = [];
        $nomistsite = [];
        $novars = [];
        $nodhcp = [];
        $keasites = [];
        $gizmosites = [];
        $nbsites = Sites::where('fields','id,name,custom_fields')->get();
        $nbsitecount = count($nbsites);
        $mistsites = Site::all();
        $gizmoscopes = Dhcp::all();
        $keascopes = SubnetV4::all();
        $prefixes = Prefixes::where('fields','id,prefix,role,_depth,children,status,scope_type,scope_id')->get();

        $count = 0;
        foreach($nbsites as $nbsite)
        {
            $count++;
            $keavars = 0;
            $gizmovars = 0;
            $neithervars = 0;
            print "********************************************************************" . PHP_EOL;
            print "{$count}/{$nbsitecount} - SITE: {$nbsite->name}..." . PHP_EOL;
            $supernets = $prefixes->where('scope_type', 'dcim.site')->where('scope_id',$nbsite->id)->where('role.id',6);
            if(count($supernets) == 0)
            {
                print "No supernets found! adding to PROBLEMS..." . PHP_EOL;
                $nosupernets[] = $nbsite->name;
                continue;
            }
            print "SUPERNETS:" . PHP_EOL;
            $kscopes = [];
            $gscopes = [];
            foreach($supernets as $supernet)
            {
                print $supernet->prefix . PHP_EOL;
                $snkscopes = $keascopes->findChildren($supernet->network(), $supernet->length());
                foreach($snkscopes as $scope)
                {
                    $kscopes[] = $scope;
                }
                $sngscopes = $gizmoscopes->findOverlap($supernet->network(), $supernet->length());
                foreach($sngscopes as $scope)
                {
                    $gscopes[] = $scope;
                }
            }
            if(count($kscopes) == 0 && count($gscopes) == 0)
            {
                print "No Gscopes or Kscopes found for site, added to PROBLEMS!" . PHP_EOL;
                $noscopes[] = $nbsite->name;
                continue;
            }
            print "KEA SCOPES:" . PHP_EOL;
            foreach($kscopes as $scope)
            {
                print $scope->subnet . PHP_EOL;
            }
            print "GIZMO SCOPES:" . PHP_EOL;
            foreach($gscopes as $scope)
            {
                print $scope->scopeID . PHP_EOL;
            }
            $mistsite = $mistsites->where('name', $nbsite->name)->first();
            if(!isset($mistsite->id))
            {
                print "No MIST SITE found! adding to problems...." . PHP_EOL;
                $nomistsite[] = $nbsite->name;
                continue;
            }
            $template = $mistsite->getGatewayTemplate();
            if(isset($template->name))
            {
                print "MIST site GATEWAY TEMPLATE: {$template->name}" . PHP_EOL;
            } else {
                print "Unable to retrieve mist site GATEWAY TEMPLATE..." . PHP_EOL;
            }
            $gateway = $mistsite->getDevices('gateway')->first();
            if(isset($gateway->name))
            {
                print "Found Gateway Router {$gateway->name}..." . PHP_EOL;
                if(isset($gateway->dhcpd_config ) && $gateway->dhcpd_config)
                {
                    print_r($gateway->dhcpd_config);
                }
            } else {
                print "Unable to find Gateway Router..." . PHP_EOL;
            }
            $vars = $mistsite->getSettings()->vars;
            if(!isset($vars->DHCP_1) && !isset($vars->DHCP_2) && !isset($vars->DHCP_3) && !isset($vars->DHCP_4))
            {
                print "MIST DHCP VARS are not set... adding to problems..." . PHP_EOL;
                $novars[] = $nbsite->name;
                continue;
            }
            print "MIST SITE VARS:" . PHP_EOL;
            if(isset($vars->DHCP_1))
            {
                print "DHCP_1: " . $vars->DHCP_1 . PHP_EOL;
            }
            if(isset($vars->DHCP_2))
            {
                print "DHCP_2: " . $vars->DHCP_2 . PHP_EOL;
            }
            if(isset($vars->DHCP_3))
            {
                print "DHCP_3: " . $vars->DHCP_3 . PHP_EOL;
            }
            if(isset($vars->DHCP_4))
            {
                print "DHCP_4: " . $vars->DHCP_4 . PHP_EOL;
            }
            if($vars->DHCP_1 == "10.251.17.56" && $vars->DHCP_2 == "10.251.17.51" && $vars->DHCP_3 == "10.251.17.52" && $vars->DHCP_4 == "10.0.192.130")
            {
                print "VARS match KEA" . PHP_EOL;
                $keavars = 1;
            } elseif($vars->DHCP_1 == "10.252.12.143" && $vars->DHCP_2 == "10.252.12.144" && $vars->DHCP_3 == "10.0.192.130" && $vars->DHCP_4 == "10.0.192.130"){
                print "VARS match GIZMO" . PHP_EOL;
                $gizmovars = 1;
            } else {
                $neithervars = 1;
            }
            if(count($kscopes) > 0 && $keavars)
            {
                print "Site is setup for KEA scopes" . PHP_EOL;
                $keasites[] = $nbsite->name;
            } elseif(count($gscopes) > 0 && $gizmovars){
                print "Site is setup for GIZMO scopes" . PHP_EOL;
                $gizmosites[] = $nbsite->name;
            } else {
                print "Site is NOT setup for Kea or Gizmo, adding to problems" . PHP_EOL;
                $nodhcp[] = $nbsite->name;
            }
        }
        print "NO SUPERNETS:" . PHP_EOL;
        print_r($nosupernets);
        print "NO SCOPES:" . PHP_EOL;
        print_r($noscopes);
        print "NO MIST SITE:" . PHP_EOL;
        print_r($nomistsite);
        print "NO VARS:" . PHP_EOL;
        print_r($novars);
        print "NO DHCP:" . PHP_EOL;
        print_r($nodhcp);
        print "KEA SITES:" . PHP_EOL;
        print_r($keasites);
        print "GIZMO SITES:" . PHP_EOL;
        print_r($gizmosites);
    }
}
