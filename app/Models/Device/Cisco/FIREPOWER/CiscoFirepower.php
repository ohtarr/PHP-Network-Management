<?php

namespace App\Models\Device\Cisco\FIREPOWER;

use App\Models\Device\Cisco\Cisco;

class CiscoFirepower extends Cisco
{
    protected static $singleTableSubclasses = [
    ];

    protected static $singleTableType = __CLASS__;

    public $discover_commands = [
    ];

    public $discover_regex = [
    ];

    //List of outputs to collect during a scan of this device.
    public $scan_outputs = [
        'run'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'show configuration all',
        ],
        'version'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'show chassis firmware',
        ],
        'inventory'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'show chassis detail',
        ],
    ];

}
