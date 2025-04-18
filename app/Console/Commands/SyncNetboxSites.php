<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Sites;
use App\Models\ServiceNow\Location;

class SyncNetboxSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:SyncNetboxSites';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Netbox sites from ServiceNow';

    public $snowlocs;
    public $netboxsites;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->syncNetboxSites();
    }

    public function getSnowLocs()
    {
        if(!$this->snowlocs)
        {
            $this->snowlocs = Location::allActive();
        }
        return $this->snowlocs;
    }

    public function getNetboxSites()
    {
        if(!$this->netboxsites)
        {
            $this->netboxsites = Sites::all();
        }
        return $this->netboxsites;
    }

    public function syncNetboxSites()
    {
        foreach($this->getNetboxSites() as $site)
        {
            print "Syncing site " . $site->name . PHP_EOL;
            unset($loc);
            $loc = $this->getNetboxSiteSnowLoc($site);
            if(!$loc)
            {
                print "NO SITE FOUND, SKIPPING!\n";
                continue;
            }
            try{
                $this->SyncAddress($site, $loc);
            } catch (\Exception $e) {
                print "Failed to Sync Address, Skipping...\n";
                continue;
            }
        }
    }

    public function getSnowLocBySysid($sysid)
    {
        return $this->getSnowLocs()->where('sys_id', $sysid)->first();
    }

    public function getSnowLocByName($name)
    {
        return $this->getSnowLocs()->where('name', $name)->first();
    }

    public function getNetboxSiteSnowLoc($site)
    {
        $loc = null;
        if(isset($site->custom_fields->SNOW_SYSID))
        {
            if($site->custom_fields->SNOW_SYSID)
            {
                $loc = $this->getSnowLocBySysid($site->custom_fields->SNOW_SYSID);
            }
        }
        if(!$loc)
        {
            $loc = $this->getSnowLocByName($site->name);
        }
        return $loc;
    }


    public function SyncAddress($site, $loc)
    {
        $fix = [];
        print "SYNCING ADDRESS for site " . $site->name . PHP_EOL;
        $custommapping = [
            'STREET_NUMBER'                     =>  'u_street_number',
            'STREET_PREDIRECTIONAL'             =>  'u_street_predirectional',
            'STREET_NAME'                       =>  'u_street_name',
            'STREET_SUFFIX'                     =>  'u_street_suffix',
            'STREET_POSTDIRECTIONAL'            =>  'u_street_postdirectional',
            'STREET2_SECONDARYUNITINDICATOR'    =>  'u_secondary_unit_indicator',
            'STREET2_SECONDARYNUMBER'           =>  'u_secondary_number',
            'CITY'                              =>  'city',
            'STATE'                             =>  'state',
            'POSTAL_CODE'                       =>  'zip',
            'COUNTRY'                           =>  'country',
        ];
        $mapping = [
            'description'           =>  'u_description',
        ];
        foreach($custommapping as $nbkey => $snkey)
        {
            if($site->custom_fields->$nbkey != $loc->$snkey)
            {
                $fix['custom_fields'][$nbkey] = $loc->$snkey;
            }
        }

        if($fix)
        {
            print "THINGS TO FIX: \n";
            print_r($fix);
            $site->update($fix);
        } else {
            print "Nothing to fix!  skipping!\n";
        }
    }
}
