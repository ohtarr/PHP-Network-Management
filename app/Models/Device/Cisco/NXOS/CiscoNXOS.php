<?php

namespace App\Models\Device\Cisco\NXOS;

use App\Models\Device\Cisco\Cisco;

class CiscoNXOS extends Cisco
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
            'input'     =>  'show interface',
        ],
        'inventory'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'show inventory all',
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
        'mac'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'show mac address-table',
        ],
    ];

    /*
     Find the NAME of this device from OUTPUTs.
     Returns string (device name).
     */
    public function getName()
    {
        $reg = "/hostname (\S+)/";
        $run = $this->getLatestOutputs('run')->data;
        if (preg_match($reg, $run, $hits)) {
            return $hits[1];
        }
    }

    /*
     Find the serial of this device from OUTPUTs.
     Returns string (device serial).
     */
    public function getSerial()
    {
        //Reg to grab the serial from the show inventory.
        $reg = "/SN:\s+(\S+)/";
        $inv = $this->getLatestOutputs('inventory')->data;
        if (preg_match($reg, $inv, $hits)) {
            return $hits[1];
        }
    }

    /*
    Find the model of this device from OUTPUTs.
    Returns string (device model).
    */
    public function getModel()
    {
        //Reg to grab the model from the show inventory.
        $reg = "/PID:\s+(\S+)/";
        $inv = $this->getLatestOutputs('inventory')->data;
        if (preg_match($reg, $inv, $hits)) {
            return $hits[1];
        }
    }
}
