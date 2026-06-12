<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\DCIM\Sites;

class AuditNetboxPrefixes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:AuditNetboxPrefixes {--fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit ACTIVE prefixes for site supernets.';

    public $sites;
    public $prefixes;
    public $failed;
    public $tomake;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $fix = $this->option('fix');
        $this->AuditNetboxPrefixes();
        print "To Make: " . PHP_EOL;
        print_r($this->tomake);
        print "Failed to generate networks: " . PHP_EOL;
        print_r($this->failed);
        if($fix)
        {
            $this->createNetboxPrefixes();
        }
    }

    public function getSites()
    {
        if(!$this->sites)
        {
            $this->sites = Sites::all();
        }
        return $this->sites;
    }

    public function getPrefixes()
    {
        if(!$this->prefixes)
        {
            $this->prefixes = Prefixes::all();
        }
        return $this->prefixes;
    }

    public function AuditNetboxPrefixes()
    {
        if(!$this->tomake)
        {
            $failed = [];
            $tomake = [];
            $sites = $this->getSites();
            $prefixes = $this->getPrefixes();
            foreach($sites as $site)
            {
                unset($generated);
                unset($tmp);
                $vlans = [];
                $generated = $site->generateSiteNetworks();
                if(!$generated)
                {
                    $failed[] = $site->name;
                    continue;
                }
                foreach($generated as $vlan => $genprefix)
                {
                    $prefix = $prefixes->where('prefix', $genprefix['network'] . "/" . $genprefix['bitmask'])->first();
                    if(!$prefix)
                    {
                        $vlans[] = $vlan;
                    }
                }
                if($vlans)
                {
                    $tmp['site'] = $site;
                    $tmp['vlans'] = $vlans;
                    $tomake[] = $tmp;
                }
            }
            $this->failed = $failed;
            $this->tomake = $tomake;
        }
        return $this->tomake;
    }

    public function createNetboxPrefixes()
    {
        foreach($this->tomake as $sitetomake)
        {
            $site = $sitetomake['site'];
            foreach($sitetomake['vlans'] as $vlanid)
            {
                print "Deploying PREFIX for vlan {$vlanid} for site {$site->name}..." . PHP_EOL;
                $site->deployActivePrefix($vlanid);
            }
        }
    }

}



