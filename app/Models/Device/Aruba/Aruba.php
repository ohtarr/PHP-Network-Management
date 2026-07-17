<?php

namespace App\Models\Device\Aruba;

use Metaclassing\SSH;

class Aruba extends \App\Models\Device\Device
{
    protected static $singleTableSubclasses = [
    ];

    protected static $singleTableType = __CLASS__;

    //List of outputs to collect during a scan of this device.
    public $scan_outputs = [
        'run'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh run',
        ],
        'version'   =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh version',
        ],
        'inventory' =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh inventory',
        ],
        'dir'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'dir',
        ],
        'cdp'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh cdp neighbor',
        ],
        'lldp'      =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh lldp neighbor',
        ],
    ];

    //Use SSH2 for connection
    public function exec_cmds($cmds, $timeout = null)
    {
        return $this->exec_cmds_2($cmds, $timeout);
    }

    /*
    Find the name of this device from DATA.
    Returns string (device name).
    */
    public function getName()
    {
        $reg = "/hostname\s+\"(\S+)\"/";
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
        $reg = "/System\s+Serial#\s+:\s+(\S+)/";
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
        $reg = "/SC\sModel#\s+:\s+(\S+)/";
        if (preg_match($reg, $this->data['inventory'], $hits)) {
            return $hits[1];
        }
    }
}
