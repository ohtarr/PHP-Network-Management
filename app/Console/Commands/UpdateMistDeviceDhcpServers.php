<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mist\Site;
use App\Models\Mist\Gateway;

class UpdateMistDeviceDhcpServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:UpdateMistDeviceDhcpServers {sitecodes : Comma separated list of Mist site codes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the gateway dhcpd_config relay servers for a list of Mist sites';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $sitecodes = explode(',', $this->argument('sitecodes'));

        $servers = [
            '{{DHCP_1}}',
            '{{DHCP_2}}',
            '{{DHCP_3}}',
            '{{DHCP_4}}',
        ];

        $config = ['type' => 'relay', 'servers' => $servers, 'fixed_bindings' => (object)[]];

        $dhcp = [
            'V102_NET_SITE_VLAN_1_PREFIX'   =>  $config,
            'V102_NET_SITE_VLAN_5_PREFIX'   =>  $config,
            'V102_NET_SITE_VLAN_9_PREFIX'   =>  $config,
            'V102_NET_SITE_VLAN_13_PREFIX'  =>  $config,
        ];

        foreach($sitecodes as $sitecode)
        {
            $sitecode = trim($sitecode);
            print "Processing MIST SITE {$sitecode}..." . PHP_EOL;
            $site = Site::findByName($sitecode);
            if(!isset($site->id))
            {
                print "MIST SITE {$sitecode} NOT FOUND... skipping." . PHP_EOL;
                continue;
            }

            $device = Gateway::where('site_id', $site->id)->first();
            if(!isset($device->id))
            {
                print "No MIST GATEWAY found for site {$sitecode}... skipping." . PHP_EOL;
                continue;
            }
            $device->getSiteDevice();

            $params = ['dhcpd_config' => $dhcp];

            print "Updating DHCP relay config for GATEWAY {$device->name} at site {$sitecode}..." . PHP_EOL;
            $device->update($params);
        }
    }
}
