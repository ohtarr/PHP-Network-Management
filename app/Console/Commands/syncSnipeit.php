<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mist\Site;
use App\Models\Mist\Device;
use App\Models\SnipeIT\Locations;
use App\Models\SnipeIT\Models;
use App\Models\SnipeIT\StatusLabels;
use App\Models\SnipeIT\Assets;
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
        //$this->syncLocations();
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
                $newloc = Locations::create($params);
                if($newloc)
                {
                    print "New Location {$newloc->id} : {$newloc->name} created Successfully!" . PHP_EOL;
                } else {
                    print "New Location FAILED to create!" . PHP_EOL;
                }
            }
        }
    }

    public function createModel($model)
    {
        $params = [
            'name'              =>  $model,
            'model_number'      =>  $model,
            'category_id'       =>  3,
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
            print "processing Mist Device {$mistdevice->name} ..." . PHP_EOL;
            unset($asset);
            unset($currentloc);
            unset($correctloc);
            unset($mistsite);
            unset($model);
            if(isset($mistdevice->serial))
            {
                print "Found Mist Serial {$mistdevice->serial}..." . PHP_EOL;
                $asset = Assets::where('search', $mistdevice->serial)->first();
            } else {
                print "Mist Device does NOT have a serial, skipping!" . PHP_EOL;
                continue;
            }
            if($asset)
            {
                //$loc = $asset->getLocation();
                if(isset($asset->assigned_to->id) && isset($asset->assigned_to->type))
                {
                    if($asset->assigned_to->type == "location")
                    {
                        $currentloc = $snipeitlocs->where('id', $asset->assigned_to->id)->first();
                    }
                }
                if(isset($mistdevice->connected))
                {
                    if($mistdevice->connected == true)
                    {
                        print "Updating last_online for asset {$asset->serial}." . PHP_EOL;
                        $params = [
                            '_snipeit_last_online_2' => Carbon::now()->toDateString(),
                        ];
                        $asset->update($params);
                    }
                }
            }
            if(isset($mistdevice->model))
            {
                $model = Models::where('name', $mistdevice->model)->first();
            }
            if($model)
            {
                print "Found SnipeIT Model {$model->name}..." . PHP_EOL;
            } else {
                print "Unable to find Model {$mistdevice->model}, Creating new Model in SnipeIT" . PHP_EOL;
                $model = $this->createModel($mistdevice->model);
            }

            if($mistdevice->site_id)
            {
                print "Mist Device assigned to Mist Site {$mistdevice->site_id}...." . PHP_EOL;
                $mistsite = $mistsites->where('id',$mistdevice->site_id)->first();
                $correctloc = $snipeitlocs->where('name', $mistsite->name)->first();
                if(!$correctloc)
                {
                    print "Unable to find CORRECT LOCATION in SNIPEIT... Skipping." . PHP_EOL;
                    continue;
                }
                if($asset)
                {
                    print "SnipeIT Asset {$asset->id} found..." . PHP_EOL;
                    //Check if ASSET is checked out, Checkout if not.
                    if(!isset($asset->assigned_to->id))
                    {
                        print "Asset is NOT checked out, Attempting to CHECK OUT asset to site {$mistsite->name} ..." . PHP_EOL;
                        $asset->checkoutToLocation($mistsite->name);
                    } else {
                        //Check if mist site matches snipeit site, if not, CHECKIN device and CHECKOUT.
                        //if(strtolower($mistsite->name) != strtolower($loc->name))
                        if($currentloc->id != $correctloc->id)
                        {
                            print "MIST SITE ({$mistsite->name}) and SNIPEIT LOCATION ({$loc->name}) do not match!  Checking In Asset" . PHP_EOL;
                            $asset->checkin();
                            $asset->checkoutToLocation($mistsite->name);
                        } 
                    }

                    //If snipeit asset status is not DEPLOYED, fix it.
                    if($asset->status_label->id != $deployedlabel->id)
                    {
                        print "Asset Label is NOT (DEPLOYED), correcting..." . PHP_EOL;
                        $params = [
                            'status_id' =>  $deployedlabel->id,
                        ];
                        $asset->update($params);
                    }
                } else {
                    //Asset does not exist, create a new one and checkout to proper location.
                    print "SnipeIT Asset NOT found, attempting to create..." . PHP_EOL;
                    if($model)
                    {
                        print "SnipeIT Model {$model->id} : {$model->name} found...creating new asset" . PHP_EOL;
                        $params = [
                            'asset_tag'                 => $mistdevice->serial,
                            'serial'                    => $mistdevice->serial,
                            'model_id'                  => $model->id,
                            'status_id'                 => $deployedlabel->id,
                            'assigned_location'         => $correctloc->id,
                        ];
                        $asset = Assets::create($params);
                        //And checkout asset to location.
                        $asset->checkoutToLocation($mistsite->name);
                    }
                }
            } else {
                //Asset doesn't exist, create new one and set to status unknown, do not checkout to a location.
                print "Mist Device is NOT assigned to a MIST SITE..." . PHP_EOL;
                if(!$asset)
                {
                    print "Asset does not exist in SnipeIT, Creating new asset and setting to UNKNOWN" . PHP_EOL;
                    $params = [
                        'asset_tag'     => $mistdevice->serial,
                        'serial'        => $mistdevice->serial,
                        'model_id'      => $model->id,
                        'status_id'     => $unknownlabel->id,
                    ];
                    $asset = Assets::create($params);
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



