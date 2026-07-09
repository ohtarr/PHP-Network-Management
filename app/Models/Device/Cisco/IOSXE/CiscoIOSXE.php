<?php

namespace App\Models\Device\Cisco\IOSXE;

use App\Models\Device\Cisco\Cisco;

class CiscoIOSXE extends Cisco
{
    protected static $singleTableSubclasses = [
    ];
    
    protected static $singleTableType = __CLASS__;

    //List of outputs to collect during a scan of this device.
    public $scan_outputs = [
        'run'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'show run',
        ],
        'version'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'show version',
        ],
        'interfaces'    =>  [
            'method'    =>  'ssh',
            'input'     =>  'show interfaces',
        ],
        'inventory'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'show inventory',
        ],
        'dir'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'dir',
        ],
        'cdp'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'show cdp neighbor detail',
        ],
        'lldp'          =>  [
            'method'    =>  'ssh',
            'input'     =>  'show lldp neighbor detail',
        ],
        'arp'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'show ip arp',
        ],
        'arpv101'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'show ip arp vrf V101:DATACENTER',
        ],
        'route'         =>  [
            'method'    =>  'ssh',
            'input'     =>  'show ip route',
        ],
        'routev101'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'show ip route vrf V101:DATACENTER',
        ],
        'routev102'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'show ip route vrf V102:OFFICE',
        ],
    ];

    public $parser = "\ohtarr\Cisco\IOS\Parser";
 
}
