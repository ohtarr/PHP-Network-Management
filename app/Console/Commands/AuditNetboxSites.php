<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Sites;
use App\Models\ServiceNowV2\Location;

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

    public $nbsites;
    public $snowlocs;
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        print_r($this->AuditSites());
        //print_r($this->AuditSnowSites());
    }

    public function getNetboxSites()
    {
        if(!$this->nbsites)
        {
            $this->nbsites = Sites::all();
        }
        return $this->nbsites;        
    }

    public function getNetboxSite($sitecode)
    {
        return $this->getNetboxSites()->where('name', $sitecode)->first();
    }

    public function getSnowLocations()
    {
        if(!$this->snowlocs)
        {
            $this->snowlocs = Location::all();
        }
        return $this->snowlocs;
    }

    public function getSnowLocation($sitecode)
    {
        return $this->getSnowLocations()->where('name', $sitecode)->first();
    }

    public function AuditSnowSites()
    {
        $missingdemob = [];
        foreach($this->getSnowLocations() as $snowloc)
        {
            if($snowloc->u_active == "false" && $snowloc->u_network_mob_date && !$snowloc->u_network_demob_date)
            {
                $missingdemob[] = $snowloc->name;
            }
        }
        return $missingdemob;
    }

    public function AuditSites()
    {
        $nosnowloc = [];
        $todelete = [];
        $maybedelete = [];
        $checkalert = [];
        $sites = $this->getNetboxSites();
        foreach($sites as $site)
        {
            $loc = $this->getSnowLocation($site->name);
            if(!$loc)
            {
                $nosnowloc[] = $site->name;
                continue;
            }
            //if site has network demob date in snow, it needs to be deleted in netbox.
            if($loc->u_network_demob_date)
            {
                $todelete[] = $site->name;
                continue;
            } else {
                //if site is marked innactive in snow and has a u_network_mob_date, but doesn't have a network demob date, it probably needs to be deleted from netbox.
                if($loc->u_active == "false" && $loc->u_network_mob_date)
                {
                    $maybedelete[] = $site->name;
                }
            }
            if($loc->u_priority > 0  && !$site->custom_fields->ALERT)
            {
                $checkalert[] = $site->name;
            }

        }
        $sitedevices = $site->devices();
        foreach($sitedevices as $device)
        {

        }
        print "TO DELETE (no snow loc):" . PHP_EOL;
        print_r($nosnowloc);
        print "TO DELETE (snow loc has a u_network_demob_date):" . PHP_EOL;
        print_r($todelete);
        print "MAYBE DELETE? (snow loc marked innactive, has a u_network_mob_date, but does NOT have a u_network_demob_date):" . PHP_EOL;
        print_r($maybedelete);
        print "Check SITE alerting (snow loc has alerting enabled, but netbox site does not):" . PHP_EOL;
        print_r($checkalert);
    }
}



