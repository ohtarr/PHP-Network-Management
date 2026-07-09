<?php

namespace App\Models\Device\Cisco;

use App\Models\Device\Device;
use App\Models\Device\Cisco\CiscoCollection as Collection;
use App\Models\Device\Cisco\IOS\CiscoIOS;
use App\Models\Device\Cisco\IOSXE\CiscoIOSXE;
use App\Models\Device\Cisco\IOSXR\CiscoIOSXR;
use App\Models\Device\Cisco\NXOS\CiscoNXOS;
use App\Models\Device\Cisco\ASA\CiscoASA;

class Cisco extends Device
{
    protected static $singleTableSubclasses = [
        CiscoIOS::class,
        CiscoIOSXE::class,
        CiscoIOSXR::class,
        CiscoNXOS::class,
        CiscoASA::class,
    ];
    protected static $singleTableType = __CLASS__;

    public $promptreg = '/\S*[#|>]\s*\z/';

    public $precli = [
        'term length 0',
        'terminal pager 0',
    ];

    public $cli_timeout = 20;

    public $discover_commands = [
        'sh version',
        'sh version running',
    ];

    public $discover_regex = [
        CiscoIOS::class     => [
            '/cisco ios software/i',
        ],
        CiscoIOSXE::class   => [
            '/ios-xe/i',
            '/package:/i',
        ],
        CiscoIOSXR::class   => [
            '/ios xr/i',
            '/iosxr/i',
        ],
        CiscoNXOS::class    => [
            '/Cisco Nexus/i',
            '/nx-os/i',
        ],
        CiscoASA::class     => [
            '/Cisco Adaptive Security Appliance/i',
        ],
    ];
    //List of outputs to collect during a scan of this device.
    public $scan_outputs = [
        'run'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh run',
        ],
        'version'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh version',
        ],
        'interfaces'    =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh interfaces',
        ],
        'inventory'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh inventory',
        ],
        'dir'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'dir',
        ],
        'cdp'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh cdp neighbor detail',
        ],
        'lldp'          =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh lldp neighbor detail',
        ],
        'mac'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh mac address-table',
        ],
        'arp'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh ip arp',
        ],
    ];

    public function newCollection(array $models = []) 
    { 
       return new Collection($models);
    }

    /*
    This method is used to establish a CLI session with a device.
    It will attempt to use Metaclassing\SSH library to work with specific models of devices that do not support ssh2.0 natively.
    Returns a Metaclassing\SSH object.
    */
/*     public function getCli($timeout = 20)
    {
        $credentials = $this->getCredentials();
        foreach ($credentials as $credential) {
            // Attempt to connect using Metaclassing\SSH library.
            try {
                $cli = $this->getSSH1($this->ip, $credential->username, $credential->passkey);
            } catch (\Exception $e) {
                //If that fails, attempt to connect using phpseclib\Net\SSH2 library.
            }
            if ($cli) {
                $this->credential_id = $credential->id;
                //$this->save();

                return $cli;
            }
        }
    } */

    /*
    Find the name of this device from DATA.
    Returns string (device name).
    */
    public function getName()
    {
        if(!isset($this->data['run']))
        {
            return null;
        }
        $reg = "/hostname (\S+)/";
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
        if(!isset($this->data['version']))
        {
            return null;
        }
        $reg = "/^Processor board ID (\S+)/m";
        if (preg_match($reg, $this->data['version'], $hits)) {
            return $hits[1];
        }
    }

    /*
    Find the model of this device from DATA.
    Returns string (device model).
    */
    public function getModel()
    {
        if(!isset($this->data['version']))
        {
            return null;
        }
        if (preg_match('/.*isco\s+(WS-\S+)\s.*/', $this->data['version'], $reg)) {
            return $reg[1];
        }
        if (preg_match('/.*isco\s+(OS-\S+)\s.*/', $this->data['version'], $reg)) {
            return $reg[1];
        }
        if (preg_match('/.*ardware:\s+(\S+),.*/', $this->data['version'], $reg)) {
            return $reg[1];
        }
        if (preg_match('/.*ardware:\s+(\S+).*/', $this->data['version'], $reg)) {
            return $reg[1];
        }
        if (preg_match('/^[c,C]isco\s(\S+)\s\(.*/m', $this->data['version'], $reg)) {
            return $reg[1];
        }
    }
}
