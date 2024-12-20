<?php

namespace App\Models\Device\Cisco\IOSXR;

use App\Models\Device\Cisco\Cisco;

class CiscoIOSXR extends Cisco
{
    protected static $singleTableSubclasses = [
    ];
    
    protected static $singleTableType = __CLASS__;

    //List of commands to run during a scan of this device.
    public $scan_cmds = [
        'run'           => 'show run',
        'version'       => 'show version',
        'interfaces'    => 'show interfaces',
        'inventory'     => 'admin show inventory',
        'dir'           => 'dir',
        'cdp'           => 'show cdp neighbor detail',
        'lldp'          => 'show lldp neighbor detail',
        'arp'           => 'show arp',
        'arpv101'       => 'show arp vrf V101:DATACENTER',
        'arpv102'       => 'show arp vrf V102:OFFICE',
        'route'         => 'show ip route',
        'routev101'     => 'show ip route vrf V101:DATACENTER',
        'routev102'     => 'show ip route vrf V102:OFFICE',
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
