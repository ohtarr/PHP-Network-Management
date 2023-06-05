<?php

namespace App\Models\Device\Juniper;

use Metaclassing\SSH;

class Juniper extends \App\Models\Device\Device
{
    protected static $singleTableSubclasses = [
    ];

    protected static $singleTableType = __CLASS__;

    //List of commands to run during a scan of this device.
    public $scan_cmds = [
        'version'		=>	'show version | display json',
        'inventory'		=>	'show chassis hardware | display json',
        'run'			=>	'show configuration | display inheritance no-comments | display json',
        'interface'		=>	'show interfaces | display json',
        'lldp'			=>	'show lldp neighbors | display json',
        'run_set'		=>	'show configuration | display set',
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
        $array = json_decode($this->data['run'], true);
        return $array['configuration']['system']['host-name'];
    }

    /*
    Find the serial of this device from DATA.
    Returns string (device serial).
    */
    public function getSerial()
    {
        $array = json_decode($this->data['inventory'], true);
        return $array["chassis-inventory"][0]["chassis"][0]["serial-number"][0]['data'];
    }

    /*
    Find the model of this device from DATA.
    Returns string (device model).
    */
    public function getModel()
    {
        $array = json_decode($this->data['version'], true);
        return $array['software-information'][0]['product-model'][0]['data'];
    }
}
