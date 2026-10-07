<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Sites;

class SiteDhcpSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:SiteDhcpSummary {sitecode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Site Dhcp Summary';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $sitecode = $this->argument('sitecode');
        $site = Sites::where('name', $sitecode)->first();
        if(!isset($site->name))
        {
            print "NETBOX site NOT FOUND!" . PHP_EOL;
            return null;
        }
        $mistsite = $site->getMistSite();
        if(!isset($mistsite->name))
        {
            print "MIST site NOT FOUND!" . PHP_EOL;
            return null;
        }
        $settings = $mistsite->getSettings();
        $template = $mistsite->getGatewayTemplate();
        if(!isset($template->name))
        {
            print "MIST GATEWAY TEMPLATE NOT FOUND!" . PHP_EOL;
            return null;
        }
        $gateway = $mistsite->getDevices('gateway')->first();
        if(!isset($gateway->name))
        {
            print "MIST GATEWAY NOT FOUND!" . PHP_EOL;
            return null;
        }
        $gizmoscopes = $site->getGizmoDhcpScopesBySupernets();
/*         foreach($gizmoscopes as $gscope){
            print $gscope->scopeID . PHP_EOL;
        } */

        $keascopes = $site->getKeaDhcpScopesBySupernets();
/*         foreach($keascopes as $kscope){
            print $kscope->subnet . PHP_EOL;
        } */

        print "*******************************************" . PHP_EOL;
        print "SITE: {$sitecode}" . PHP_EOL;
        print "MIST GATEWAY TEMPLATE: " . $template->name . PHP_EOL;
        print "DHCP_1: " . $settings->vars->DHCP_1 . PHP_EOL;
        print "DHCP_2: " . $settings->vars->DHCP_2 . PHP_EOL;
        print "DHCP_3: " . $settings->vars->DHCP_3 . PHP_EOL;
        print "DHCP_4: " . $settings->vars->DHCP_4 . PHP_EOL;
        print "GATEWAY DHCP SETTINGS:" . PHP_EOL;
        print_r($gateway->dhcpd_config);
        print "GIZMO SCOPES: " . count($gizmoscopes) . PHP_EOL;
        print "KEA SCOPES: " . count($keascopes) . PHP_EOL;
    }
}
