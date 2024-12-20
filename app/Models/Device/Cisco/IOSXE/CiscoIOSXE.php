<?php

namespace App\Models\Device\Cisco\IOSXE;

use App\Models\Device\Cisco\Cisco;

class CiscoIOSXE extends Cisco
{
    protected static $singleTableSubclasses = [
    ];
    
    protected static $singleTableType = __CLASS__;

    //List of commands to run during a scan of this device.
    public $scan_cmds = [
        'run'           => 'show run',
        'version'       => 'show version',
        'interfaces'    => 'show interfaces',
        'inventory'     => 'show inventory',
        'dir'           => 'dir',
        'cdp'           => 'show cdp neighbor detail',
        'lldp'          => 'show lldp neighbor detail',
        'arp'           => 'show ip arp',
        'arpv101'       => 'show ip arp vrf V101:DATACENTER',
        'route'         => 'show ip route',
        'routev101'     => 'show ip route vrf V101:DATACENTER',
        'routev102'     => 'show ip route vrf V102:OFFICE',
    ];

    public $parser = "\ohtarr\Cisco\IOS\Parser";
 
}
