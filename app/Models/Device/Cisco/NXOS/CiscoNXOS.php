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
        $output = $this->getLatestOutputs('run');
        if (!$output || empty($output->data)) {
            return null;
        }
        if (preg_match($reg, $output->data, $hits)) {
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
        $output = $this->getLatestOutputs('inventory');
        if (!$output || empty($output->data)) {
            return null;
        }
        if (preg_match($reg, $output->data, $hits)) {
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
        $output = $this->getLatestOutputs('inventory');
        if (!$output || empty($output->data)) {
            return null;
        }
        if (preg_match($reg, $output->data, $hits)) {
            return $hits[1];
        }
    }

    /*
     Find the MAC address of this device from OUTPUTs.
     Prefers the management interface (mgmt0); falls back to the first
     interface with a MAC in the latest 'show interface' output.
     Returns string (lowercase, no separators) or null.
     */
    public function getMac()
    {
        $output = $this->getLatestOutputs('interfaces');
        if (!$output || empty($output->data)) {
            return null;
        }

        $currentIf = null;
        $macs = [];
        foreach (explode("\n", $output->data) as $line) {
            if (preg_match('/^\s*(\S+)\s+is\s+(?:administratively\s+)?(?:up|down)/i', $line, $m)) {
                $currentIf = $m[1];
                continue;
            }
            if ($currentIf && !isset($macs[$currentIf]) && preg_match('/address(?:\s+is|:)\s+([0-9a-fA-F.:\-]+)/i', $line, $m)) {
                $macs[$currentIf] = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $m[1]));
            }
        }

        if (empty($macs)) {
            return null;
        }

        foreach ($macs as $ifName => $mac) {
            if (stripos($ifName, 'mgmt0') === 0) {
                return $mac;
            }
        }

        return reset($macs);
    }
}
