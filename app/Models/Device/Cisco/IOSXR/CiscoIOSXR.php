<?php

namespace App\Models\Device\Cisco\IOSXR;

use App\Models\Device\Cisco\Cisco;

class CiscoIOSXR extends Cisco
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
            'input'     =>  'admin show inventory',
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
            'input'     =>  'show arp',
        ],
        'arpv101'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'show arp vrf V101:DATACENTER',
        ],
        'arpv102'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'show arp vrf V102:OFFICE',
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
