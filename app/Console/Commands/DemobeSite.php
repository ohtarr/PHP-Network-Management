<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Sites;

class DemobeSite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:DemobeSite {--sitecode=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Completely Demobe a site';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->demobeSite();
    }

    public function demobeSite()
    {
        if(!isset($this->options()['sitecode']))
        {
            print "NO SITECODE PROVIDED!" . PHP_EOL;
            return false;
        }
        $sitecode = $this->options()['sitecode'];
        $site = Sites::where('name', $sitecode)->first();

        if(!isset($site->id))
        {
            print "NETBOX SITE NOT FOUND..." . PHP_EOL;
            return false;
        }

        $provprefix = $site->getProvisioningSupernet();
        $as = $site->getPrimaryAsn();

        $prefix1 = $site->getWiredPrefix();
        if(isset($prefix1->id))
        {
            $range1 = $prefix1->getDhcpIpRange();
        }
        if(isset($range1->id))
        {
            $scope1 = $range1->getDhcpScope();
        }

        $prefix5 = $site->getWirelessPrefix();
        if(isset($prefix5->id))
        {
            $range5 = $prefix5->getDhcpIpRange();
        }
        if(isset($range5->id))
        {
            $scope5 = $range5->getDhcpScope();
        }

        $prefix9 = $site->getVoicePrefix();
        if(isset($prefix9->id))
        {
            $range9 = $prefix9->getDhcpIpRange();
        }
        if(isset($range9->id))
        {
            $scope9 = $range9->getDhcpScope();
        }

        $prefix13 = $site->getRestrictedPrefix();
        if(isset($prefix13->id))
        {
            $range13 = $prefix13->getDhcpIpRange();
        }
        if(isset($range13->id))
        {
            $scope13 = $range13->getDhcpScope();
        }

        $confirm = $this->ask("Are you sure you want to demobe site {$site->name}? (must type `yes`)");

        if(strtolower($confirm) != "yes")
        {
            print "Cancelling Demobe..." . PHP_EOL;            
            return false;
        }

        print "Demobilizing Site {$site->name} ID: {$site->id}..." . PHP_EOL;

        if(isset($scope1->scopeID))
        {
            print "Deleting SCOPE {$scope1->scopeID}..." . PHP_EOL;
            $scope1->delete();
        }

        if(isset($scope5->scopeID))
        {
            print "Deleting SCOPE {$scope5->scopeID}..." . PHP_EOL;
            $scope5->delete();
        }

        if(isset($scope9->scopeID))
        {
            print "Deleting SCOPE {$scope9->scopeID}..." . PHP_EOL;
            $scope9->delete();
        }

        if(isset($scope13->scopeID))
        {
            print "Deleting SCOPE {$scope13->scopeID}..." . PHP_EOL;
            $scope13->delete();
        }

        if(isset($range1->id))
        {
            print "Deleting RANGE {$range1->display} ID: {$range1->id}..." . PHP_EOL;
            $range1->delete();
        }

        if(isset($range5->id))
        {
            print "Deleting RANGE {$range5->display} ID: {$range5->id}..." . PHP_EOL;
            $range5->delete();
        }

        if(isset($range9->id))
        {
            print "Deleting RANGE {$range9->display} ID: {$range9->id}..." . PHP_EOL;
            $range9->delete();
        }

        if(isset($range13->id))
        {
            print "Deleting RANGE {$range13->display} ID: {$range13->id}..." . PHP_EOL;
            $range13->delete();
        }

        if(isset($prefix1->id))
        {
            print "Deleting PREFIX {$prefix1->prefix} ID: {$prefix1->id}..." . PHP_EOL;
            $prefix1->delete();
        }

        if(isset($prefix5->id))
        {
            print "Deleting PREFIX {$prefix5->prefix} ID: {$prefix5->id}..." . PHP_EOL;
            $prefix5->delete();
        }

        if(isset($prefix9->id))
        {
            print "Deleting PREFIX {$prefix9->prefix} ID: {$prefix9->id}..." . PHP_EOL;
            $prefix9->delete();
        }

        if(isset($prefix13->id))
        {
            print "Deleting PREFIX {$prefix13->prefix} ID: {$prefix13->id}..." . PHP_EOL;
            $prefix13->delete();
        }

        $params = [
            'status'        =>  "available",
            'scope_type'    =>  null,
            'scope_id'      =>  null,
            'description'   =>  "",
        ];

        if(isset($provprefix->id))
        {
            print "Resetting PREFIX {$provprefix->prefix} ID: {$provprefix->id} back to AVAILABLE..." . PHP_EOL;
            $provprefix->update($params);
        }

        foreach($site->locations() as $loc)
        {
            print "Deleting LOCATION {$loc->name} ID: {$loc->id}..." . PHP_EOL;
            $loc->delete();
        }

        print "Deleting AS {$as->asn} ID: {$as->id}..." . PHP_EOL;
        $as->delete();

        print "Deleting SITE {$site->name} ID: {$site->id}..." . PHP_EOL;
        $site->delete();
    }
}
