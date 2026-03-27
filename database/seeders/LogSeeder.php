<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Log\Log;
use Illuminate\Support\Carbon;

class LogSeeder extends Seeder
{
    /**
     * Seed the logs table with sample entries.
     *
     * @return void
     */
    public function run()
    {
        $entries = [
            // ManagementController
            ['controller' => 'ManagementController', 'method' => 'getSiteSummary',    'message' => 'Successfully retrieved NETBOX SITE.',                          'status' => true,  'hours_ago' => 1],
            ['controller' => 'ManagementController', 'method' => 'getSiteSummary',    'message' => 'Successfully retrieved MIST SITE.',                            'status' => true,  'hours_ago' => 1],
            ['controller' => 'ManagementController', 'method' => 'getSiteSummary',    'message' => 'Unable to find NETBOX SITE.',                                  'status' => false, 'hours_ago' => 2],
            ['controller' => 'ManagementController', 'method' => 'syncNetboxDevice',  'message' => 'Webhook received for Netbox device ID 1042 (event: updated).', 'status' => true,  'hours_ago' => 3],
            ['controller' => 'ManagementController', 'method' => 'syncNetboxDevice',  'message' => 'Netbox device ID 9999 not found.',                             'status' => false, 'hours_ago' => 5],

            // ProvisioningController
            ['controller' => 'ProvisioningController', 'method' => 'deployNetboxSite',    'message' => 'Successfully deployed Netbox site OMA001.',                'status' => true,  'hours_ago' => 6],
            ['controller' => 'ProvisioningController', 'method' => 'deployNetboxDevices', 'message' => 'Successfully deployed 4 devices to Netbox site OMA001.',   'status' => true,  'hours_ago' => 6],
            ['controller' => 'ProvisioningController', 'method' => 'deployDhcpScopes',    'message' => 'Failed to deploy DHCP scope for site DEN002 VLAN 10.',     'status' => false, 'hours_ago' => 8],
            ['controller' => 'ProvisioningController', 'method' => 'deployMistSite',      'message' => 'Successfully deployed Mist site for sitecode OMA001.',     'status' => true,  'hours_ago' => 10],
            ['controller' => 'ProvisioningController', 'method' => 'deployMistDevices',   'message' => 'Successfully claimed 3 Mist devices for site OMA001.',     'status' => true,  'hours_ago' => 10],

            // DeprovisioningController
            ['controller' => 'DeprovisioningController', 'method' => 'deleteMistSite',       'message' => 'Successfully deleted Mist site for sitecode PHX003.',   'status' => true,  'hours_ago' => 24],
            ['controller' => 'DeprovisioningController', 'method' => 'unassignMistDevices',   'message' => 'Successfully unassigned 2 Mist devices from PHX003.',   'status' => true,  'hours_ago' => 24],
            ['controller' => 'DeprovisioningController', 'method' => 'deleteSiteDhcpScopes',  'message' => 'Failed to delete DHCP scopes for site PHX003.',         'status' => false, 'hours_ago' => 25],

            // SyncDeviceDnsJob
            ['controller' => 'SyncDeviceDnsJob', 'method' => 'handle',       'message' => 'SyncDeviceDnsJob completed for Netbox device ID 1042 (event: updated).', 'status' => true,  'hours_ago' => 3],
            ['controller' => 'SyncDeviceDnsJob', 'method' => 'handleUpsert', 'message' => 'Created DNS A record: oma001rwa01.net.kiewitplaza.com -> 10.1.1.1.',       'status' => true,  'hours_ago' => 3],
            ['controller' => 'SyncDeviceDnsJob', 'method' => 'handleDeleted','message' => 'Deleted DNS records for deleted device den002rwa01.',                      'status' => true,  'hours_ago' => 48],

            // DiscoverDeviceJob
            ['controller' => 'DiscoverDeviceJob', 'method' => 'handle', 'message' => 'Device discovery completed for device ID 55.',  'status' => true,  'hours_ago' => 72],
            ['controller' => 'DiscoverDeviceJob', 'method' => 'handle', 'message' => 'Device discovery failed for device ID 88 — unable to establish CLI.', 'status' => false, 'hours_ago' => 96],

            // ValidationController
            ['controller' => 'ValidationController', 'method' => 'validateNetboxSite', 'message' => 'Netbox site OMA001 passed all validation checks.',  'status' => true,  'hours_ago' => 12],
            ['controller' => 'ValidationController', 'method' => 'validateNetboxSite', 'message' => 'Netbox site DEN002 failed validation: missing primary IP on 2 devices.', 'status' => false, 'hours_ago' => 36],
        ];

        foreach ($entries as $entry) {
            Log::create([
                'controller' => $entry['controller'],
                'method'     => $entry['method'],
                'message'    => $entry['message'],
                'status'     => $entry['status'],
                'created_at' => Carbon::now()->subHours($entry['hours_ago']),
                'updated_at' => Carbon::now()->subHours($entry['hours_ago']),
            ]);
        }
    }
}
