<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mist\Site;
use App\Models\Mist\Device;
use App\Models\SnipeIT\Locations;
use App\Models\SnipeIT\Models;
use App\Models\SnipeIT\StatusLabels;
use App\Models\SnipeIT\Assets;
use App\Models\SnipeIT\Fieldsets;
use \Carbon\Carbon;

class syncSnipeit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:syncSnipeit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync data to SnipeIT';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->syncModels();
        $this->syncLocations();
        $this->syncAssets();
    }

    public function syncLocations()
    {
        $mistsites = Site::all();
        foreach($mistsites as $mistsite)
        {
            unset($loc);
            unset($newloc);
            print "*********************************************************" . PHP_EOL;
            print "Processing Mist Site {$mistsite->name} ...." . PHP_EOL;
            $loc = Locations::where('name',$mistsite->name)->first();
            if($loc)
            {
                print "Location {$mistsite->name} already exists in SnipeIT... Skipping..." . PHP_EOL;
            } else {
                //Create new SnipeIT Location
                print "Location {$mistsite->name} does NOT exist in SnipeIT... Creating..." . PHP_EOL;
                $params = [
                    'name'  =>  $mistsite->name,
                ];
                try {
                    $newloc = Locations::create($params);
                    if($newloc)
                    {
                        print "New Location {$newloc->id} : {$newloc->name} created Successfully!" . PHP_EOL;
                    } else {
                        print "New Location FAILED to create!" . PHP_EOL;
                    }
                } catch (\Exception $e) {
                    print "ERROR creating Location {$mistsite->name}: " . $e->getMessage() . PHP_EOL;
                }
            }
        }
    }

    public function syncModels()
    {
        $fieldset = Fieldsets::all()->where('name','STATUS')->first();
        $models = Models::all();
        foreach($models as $model)
        {
            if(!(isset($model->fieldset_id) && $model->fieldset_id))
            {
                $params = [
                    'fieldset_id'   =>  $fieldset->id,
                ];
                try {
                    $model->update($params);
                } catch (\Exception $e) {
                    print "ERROR updating model {$model->name}: " . $e->getMessage() . PHP_EOL;
                }
            }
        }
    }

    public function createModel($model)
    {
        $snipeitfieldset = Fieldsets::all()->where('name','STATUS')->first();
        $params = [
            'name'              =>  $model,
            'model_number'      =>  $model,
            'category_id'       =>  3,
            'fieldset_id'       =>  $snipeitfieldset->id,
        ];
        return Models::create($params);
    }

    public function syncAssets()
    {
        $start = microtime(true);
        $deployedlabel = StatusLabels::where('name','Deployed')->first();
        $unknownlabel = StatusLabels::where('name','Unknown')->first();
        $snipeitlocs = Locations::all();
        $mistdevices = Device::where('vc',true)->get();
        $mistdevicecount = count($mistdevices);
        $mistsites = Site::all();
        $count = 0;
        foreach($mistdevices as $mistdevice)
        {
            $count++;
            print "************************ {$count} / $mistdevicecount ***************************************" . PHP_EOL;
            print "Processing Mist Device {$mistdevice->name} ..." . PHP_EOL;
            unset($asset);
            unset($currentloc);
            unset($correctloc);
            unset($mistsite);
            unset($model);
            unset($vcmaster);
            unset($siteid);

            // 1. Skip if no serial
            if(!isset($mistdevice->serial))
            {
                print "Mist Device does NOT have a serial, skipping!" . PHP_EOL;
                continue;
            }
            print "Found Mist Serial {$mistdevice->serial}..." . PHP_EOL;

            // 2. Look up asset in SnipeIT by serial
            $asset = Assets::where('search', $mistdevice->serial)->first();

            // 3. Determine correct SnipeIT location from Mist site
            $vcmaster = $mistdevices->where('mac', $mistdevice->vc_mac)->first();
            $siteid = $vcmaster ? $vcmaster->site_id : $mistdevice->site_id;
            if($siteid)
            {
                $mistsite = $mistsites->where('id', $siteid)->first();
                if($mistsite)
                {
                    $correctloc = $snipeitlocs->where('name', $mistsite->name)->first();
                }
            }

            if($asset)
            {
                // 4a. If device is NOT online, skip it
                print "SnipeIT Asset {$asset->id} found..." . PHP_EOL;
                if($mistdevice->connected == false)
                {
                    print "Mist Device is NOT online, skipping!" . PHP_EOL;
                    continue;
                }

                // 4b. Device IS online — update last_online
                print "Device is online. Updating last_online for asset {$asset->serial}." . PHP_EOL;
                try {
                    $asset->update(['_snipeit_last_online_2' => Carbon::now()->toDateString()]);
                } catch (\Exception $e) {
                    print "ERROR updating last_online for asset {$asset->serial}: " . $e->getMessage() . PHP_EOL;
                }

                // Ensure we have a correct location to work with
                if(!$correctloc)
                {
                    print "Unable to find correct location in SnipeIT, skipping." . PHP_EOL;
                    continue;
                }

                // Determine current checked-out location (if any)
                $currentloc = null;
                if(isset($asset->assigned_to->id) && isset($asset->assigned_to->type) && $asset->assigned_to->type == "location")
                {
                    $currentloc = $snipeitlocs->where('id', $asset->assigned_to->id)->first();
                }

                if($currentloc)
                {
                    // Asset IS checked out — verify it's the correct site
                    if($currentloc->id != $correctloc->id)
                    {
                        print "MIST SITE ({$mistsite->name}) and SNIPEIT LOCATION ({$currentloc->name}) do not match! Checking In and re-checking Out asset." . PHP_EOL;
                        try {
                            $asset->checkin([]);
                        } catch (\Exception $e) {
                            print "ERROR checking in asset {$asset->serial}: " . $e->getMessage() . PHP_EOL;
                        }
                        try {
                            $asset->checkoutToLocation($mistsite->name);
                        } catch (\Exception $e) {
                            print "ERROR checking out asset {$asset->serial} to {$mistsite->name}: " . $e->getMessage() . PHP_EOL;
                        }
                    } else {
                        print "Asset is checked out to correct location ({$currentloc->name}). No action needed." . PHP_EOL;
                    }
                } else {
                    // Asset is NOT checked out — check it out to the correct site
                    print "Asset is NOT checked out. Checking out to site {$mistsite->name} ..." . PHP_EOL;
                    try {
                        $asset->checkoutToLocation($mistsite->name);
                    } catch (\Exception $e) {
                        print "ERROR checking out asset {$asset->serial} to {$mistsite->name}: " . $e->getMessage() . PHP_EOL;
                    }
                }

                // If snipeit asset status is not DEPLOYED, fix it
                if($asset->status_label->id != $deployedlabel->id)
                {
                    print "Asset status label is NOT (Deployed), correcting..." . PHP_EOL;
                    try {
                        $asset->update(['status_id' => $deployedlabel->id]);
                    } catch (\Exception $e) {
                        print "ERROR updating status label for asset {$asset->serial}: " . $e->getMessage() . PHP_EOL;
                    }
                }

            } else {
                // 5. Asset does NOT exist — ensure model exists, then create asset
                print "SnipeIT Asset NOT found, attempting to create..." . PHP_EOL;

                if(isset($mistdevice->model))
                {
                    $model = Models::where('name', $mistdevice->model)->first();
                }
                if($model)
                {
                    print "Found SnipeIT Model {$model->name}..." . PHP_EOL;
                } else {
                    print "Unable to find Model {$mistdevice->model}, creating new Model in SnipeIT..." . PHP_EOL;
                    try {
                        $model = $this->createModel($mistdevice->model);
                    } catch (\Exception $e) {
                        print "ERROR creating Model {$mistdevice->model}: " . $e->getMessage() . PHP_EOL;
                    }
                }

                if(!$model)
                {
                    print "Failed to find or create SnipeIT Model, skipping." . PHP_EOL;
                    continue;
                }

                if($correctloc && $mistsite)
                {
                    // Has a known site — create and check out to correct location
                    print "SnipeIT Model {$model->id} : {$model->name} found. Creating new asset and checking out to {$mistsite->name}..." . PHP_EOL;
                    $params = [
                        'asset_tag'  => $mistdevice->serial,
                        'serial'     => $mistdevice->serial,
                        'model_id'   => $model->id,
                        'status_id'  => $deployedlabel->id,
                    ];
                    try {
                        $asset = Assets::create($params);
                    } catch (\Exception $e) {
                        print "ERROR creating asset {$mistdevice->serial}: " . $e->getMessage() . PHP_EOL;
                        continue;
                    }
                    try {
                        $asset->checkoutToLocation($mistsite->name);
                    } catch (\Exception $e) {
                        print "ERROR checking out asset {$asset->serial} to {$mistsite->name}: " . $e->getMessage() . PHP_EOL;
                    }
                } else {
                    // No known site — create with Unknown status, no checkout
                    print "Mist Device is NOT assigned to a Mist Site. Creating asset with Unknown status..." . PHP_EOL;
                    $params = [
                        'asset_tag'  => $mistdevice->serial,
                        'serial'     => $mistdevice->serial,
                        'model_id'   => $model->id,
                        'status_id'  => $unknownlabel->id,
                    ];
                    try {
                        Assets::create($params);
                    } catch (\Exception $e) {
                        print "ERROR creating asset {$mistdevice->serial}: " . $e->getMessage() . PHP_EOL;
                    }
                }
            }
            //if($count >= 10)
            //{
            //    break;
            //}
        }
        $end = microtime(true);
        $duration = $end - $start;
        print "Completed in {$duration} seconds." . PHP_EOL;
    }
}



