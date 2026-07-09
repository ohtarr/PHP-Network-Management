<?php

namespace App\Models\Device\Ubiquiti;

use phpseclib\Net\SSH2;

class Ubiquiti extends \App\Models\Device\Device
{
    protected static $singleTableSubclasses = [
    ];

    protected static $singleTableType = __CLASS__;

    //List of outputs to collect during a scan of this device.
    public $scan_outputs = [
        'run'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'cat /tmp/system.cfg',
        ],
        'version'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'cat /etc/version',
        ],
        'inventory'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'cat /etc/board.info',
        ],
        'wstalist'      =>  [
            'method'    =>  'ssh',
            'input'     =>  'wstalist',
        ],
    ];

    /*
    Find the name of this device from DATA.
    Returns string (device name).
    */
    public function getName()
    {
        $reg = "/resolv.host.1.name=(\S+)/";
        if (preg_match($reg, $this->data['run'], $hits)) {
            return $hits[1];
        }
    }

    /*
    Find the serial of this device from DATA.
    Returns string (device serial).
    */
    public function getSerial()
    {
        $reg = "/board.hwaddr=(\S+)/";
        if (preg_match($reg, $this->data['inventory'], $hits)) {
            return $hits[1];
        }
    }

    /*
    Find the model of this device from DATA.
    Returns string (device model).
    */
    public function getModel()
    {
        $reg = "/board.model=(\S+)/";
        if (preg_match($reg, $this->data['inventory'], $hits)) {
            return $hits[1];
        }
    }
}
