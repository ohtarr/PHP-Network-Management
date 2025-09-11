<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\VirtualChassis;
use App\Models\Gizmo\DNS\A;
use App\Models\Gizmo\DNS\Cname;

class fixVcs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:fixVcs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Virtual Chassis for switches missing them';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        $devices = Devices::where('virtual_chassis_member', 'false')->where('limit','1000')->where('role_id',1)->where('name__ic','_')->get();
        $reg = '/(\S+)_(\d+)/';
        foreach($devices as $device)
        {
            print "Processing device {$device->name}..." . PHP_EOL;
            unset($hits);
            unset($vc);
            if(!isset($device->name))
            {
                print "Name doesn't exist, skipping..." . PHP_EOL;
                continue;
            }
            preg_match($reg, $device->name, $hits);

            if(!isset($hits[1]) || !isset($hits[2]))
            {
                print "Reg Match Failure, skipping..." . PHP_EOL;
                continue;
            }
            $basename = $hits[1];
            $memberid = $hits[2];
            $vc = VirtualChassis::where('name__ie', $basename)->first();
            if(!isset($vc->id))
            {
                print "VC NOT found...attempting to create" . PHP_EOL;
                $body = [
                    'name'  =>  $basename,
                ];
                $vc = VirtualChassis::create($body);
            }
            if(!isset($vc->id))
            {
                print "Was unable to find or create VC, skipping!..." . PHP_EOL;
                continue;
            }
            print "Found VC {$vc->id} {$vc->name}...adding device to VC" . PHP_EOL;

            $body = [
                'virtual_chassis'   =>  $vc->id,
                'vc_position'       =>  $memberid,
            ];
            $updateddevice = $device->update($body);
            if(isset($updateddevice->virtual_chassis->id))
            {
                print "Device {$device->name} successfully added to VC {$vc->name}..." . PHP_EOL;
            } else {
                print "Device {$device->name} failed to add to VC {$vc->name}..." . PHP_EOL;
                continue;
            }

            if($memberid == 0 && !isset($vc->master->id))
            {
                print "Updating MASTER on VC {$vc->name}..." . PHP_EOL;
                $body = [
                    'master'    =>  $device->id,
                ];
                $updatedvc = $vc->update($body);
                if($updatedvc->master->id == $device->id)
                {
                    print "Master was successfully updated on {$vc->name}..." . PHP_EOL;
                } else {
                    print "Master FAILED to update on {$vc->name}..." . PHP_EOL;
                }
            }
            //break;
        }
    }


}
