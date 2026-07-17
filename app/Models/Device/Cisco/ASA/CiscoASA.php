<?php

namespace App\Models\Device\Cisco\ASA;

use App\Models\Device\Cisco\Cisco;
use phpseclib3\Net\SSH2;

class CiscoASA extends Cisco
{
    protected static $singleTableSubclasses = [
    ];

    protected static $singleTableType = __CLASS__;

    public $promptreg = '/.*\S*[#|>].*/';

    public $precli = [
        'terminal pager 0',
    ];

    public $discover_commands = [
    ];

    public $discover_regex = [
    ];

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
        'conn'          =>  [
            'method'    =>  'ssh',
            'input'     =>  'show conn count',
        ],
        'interface'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'show interface',
        ],
    ];

	public function exec_cmds($cmds, $timeout = null)
    {
        return $this->exec_cmds_netmiko($cmds);
    }

}
