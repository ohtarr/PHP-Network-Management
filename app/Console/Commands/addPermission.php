<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Bouncer;

class addPermission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'LaravelAzure:addPermission {type} {role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add permissions to Bouncer Authorization system.  "type" is either "read" or "write".  "role" is assigned roles in Azure application.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $type = $this->argument('type');
        $role = $this->argument('role');
        $this->assignAdminGroupBouncerRoles($type,$role);
    }

    protected function assignAdminGroupBouncerRoles($type,$role)
    {
        // Assign Network Engineer to Admin.


        echo 'Starting Assigning Permissions to '.$role.PHP_EOL;
        if(strtolower($type) == "write")
        {
            $tasks = [
                'create',
                'read',
                'update',
                'delete',
            ];
        } elseif(strtolower($type) == "read"){
            $tasks = [
                'read',
            ];
        } else {
            print "Invalid TYPE...\n";
            return false;
        }
        
        $objects = [
            \App\Models\Device\Device::class,
            \App\Models\Device\Aruba\Aruba::class,
            \App\Models\Device\Cisco\Cisco::class,
            \App\Models\Device\Cisco\IOS\CiscoIOS::class,
            \App\Models\Device\Cisco\IOSXE\CiscoIOSXE::class,
            \App\Models\Device\Cisco\IOSXR\CiscoIOSXR::class,
            \App\Models\Device\Cisco\NXOS\CiscoNXOS::class,
            \App\Models\Device\Juniper\Juniper::class,
            \App\Models\Device\Opengear\Opengear::class,
            \App\Models\Device\Ubiquiti\Ubiquiti::class,
            \App\Models\Location\Site\Site::class,
            \App\Models\Location\Address\Address::class,
            \App\Models\Location\Building\Building::class,
            \App\Models\Location\Room\Room::class,
//            \App\Models\ServiceNow\Incident\ServiceNowIncident::class,
            \App\Models\Mist\Device::class,
            \App\Models\Mist\Site::class,
            //END-OF-PERMISSION-TYPES
        ];

        foreach ($objects as $object) {
            foreach ($tasks as $task) {
                Bouncer::allow($role)->to($task, $object);
            }
        }

        echo 'Finished Assigning Permissions'.PHP_EOL;
    }
}
