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

    public function getName()
    {
        $output = $this->getLatestOutputs('run');
        if (!$output || empty($output->data)) {
            return null;
        }
        if (preg_match('/hostname (\S+)/', $output->data, $hits)) {
            return $hits[1];
        }
    }

    public function getModel()
    {
        $output = $this->getLatestOutputs('version');
        if (!$output || empty($output->data)) {
            return null;
        }
        if (preg_match('/[Hh]ardware:\s+(\S+),/', $output->data, $hits)) {
            return $hits[1];
        }
        if (preg_match('/[Hh]ardware:\s+(\S+)/', $output->data, $hits)) {
            return $hits[1];
        }
    }

    public function getSerial()
    {
        $output = $this->getLatestOutputs('version');
        if (!$output || empty($output->data)) {
            return null;
        }
        if (preg_match('/Serial Number:\s*(\S+)/', $output->data, $hits)) {
            return $hits[1];
        }
    }

    public function getMac()
    {
        $output = $this->getLatestOutputs('interface');
        if (!$output || empty($output->data)) {
            return null;
        }

        $reg = '/^Interface (\S+) "[^"]*",.*?^\s*MAC address (\S+),/ms';
        if (!preg_match_all($reg, $output->data, $matches, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            if (stripos($match[1], 'Management') === 0) {
                return strtolower(preg_replace('/[^a-fA-F0-9]/', '', $match[2]));
            }
        }

        return strtolower(preg_replace('/[^a-fA-F0-9]/', '', $matches[0][2]));
    }

}
