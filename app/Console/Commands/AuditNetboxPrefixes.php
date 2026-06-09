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
    protected $signature = 'netman:AuditNetboxPrefixes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit ACTIVE prefixes for site supernets.';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $sites = Sites::all();
        $prefixes = Prefixes::all();
        foreach($sites as $site)
        {
            unset($generated);
            //$supernet = $site->getProvisioningSupernet();
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
                    $tomake[$site->name][] = $vlan;
                }
            }
        }
        print_r($failed);
        print_r($tomake);
    }

}



