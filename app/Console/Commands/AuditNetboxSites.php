<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Sites;
use App\Models\ServiceNow\Location;

class AuditNetboxSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:AuditNetboxSites';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit Netbox compared to live systems.';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        print_r($this->AuditSites());
    }

    public function AuditSites()
    {
        $sites = Sites::all();
        $locs = Location::all();
        foreach($sites as $site)
        {
            $loc = $locs->where('name', $site->name)->first();
            if(!$loc)
            {
                $todelete[] = $site->name;
                continue;
            }
            if($loc->u_network_demob_date)
            {
                $todelete[] = $site->name;
                continue;
            }
        }
        return $todelete;
    }

}



