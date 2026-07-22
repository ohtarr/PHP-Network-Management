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

    protected array $mapping = [
        'description'   =>  'u_description',
        'latitude'      =>  'latitude',
        'longitude'     =>  'longitude',
    ];

    protected array $custommapping = [
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
            $this->snowlocs = Location::allPremobeAndActive();
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
            $this->info("Syncing site {$site->name}");
            $loc = $this->getNetboxSiteSnowLoc($site);
            if(!$loc)
            {
                $this->warn("NO LOC FOUND, SKIPPING!");
                continue;
            }
            try{
                $this->info("Found LOC {$loc->name}...Syncing...");
                $this->SyncAddress($site, $loc);
            } catch (\Exception $e) {
                $this->error("Failed to Sync Address, Skipping...");
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
        if(!empty($site->custom_fields->SNOW_SYSID))
        {
            $loc = $this->getSnowLocBySysid($site->custom_fields->SNOW_SYSID);
        }
        if(!$loc)
        {
            $loc = $this->getSnowLocByName($site->name);
        }
        return $loc;
    }

    private function truncateCoord($value): string
    {
        return preg_replace('/(\.\d{6})\d+/', '$1', (string)(float)$value);
    }

    public function SyncAddress($site, $loc)
    {
        $fix = [];
        $this->info("SYNCING ADDRESS for site {$site->name}");
        foreach($this->mapping as $nbkey => $snkey)
        {
            if(in_array($nbkey, ['latitude', 'longitude']))
            {
                $nbval = $this->truncateCoord($site->$nbkey);
                $snval = $this->truncateCoord($loc->$snkey);
                if($nbval != $snval)
                {
                    $fix[$nbkey] = $snval;
                }
            } else {
                if($site->$nbkey != trim($loc->$snkey))
                {
                    $fix[$nbkey] = trim($loc->$snkey);
                }
            }
        }
        foreach($this->custommapping as $nbkey => $snkey)
        {
            if($site->custom_fields->$nbkey != $loc->$snkey)
            {
                $fix['custom_fields'][$nbkey] = $loc->$snkey;
            }
        }
        if($fix)
        {
            $this->info("THINGS TO FIX:");
            print_r($fix);
            $site->update($fix);
        } else {
            $this->info("Nothing to fix!  skipping!");
        }
    }
}
