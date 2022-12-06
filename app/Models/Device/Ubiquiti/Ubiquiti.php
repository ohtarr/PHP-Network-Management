<?php

namespace App\Models\Device\Ubiquiti;

use phpseclib\Net\SSH2;

class Ubiquiti extends \App\Models\Device\Device
{
    protected static $singleTableSubclasses = [
    ];

    protected static $singleTableType = __CLASS__;

    //List of commands to run during a scan of this device.
    public $scan_cmds = [
        'run'               => 'cat /tmp/system.cfg',
        'version'           => 'cat /etc/version',
        'inventory'         => 'cat /etc/board.info',
        'wstalist'          => 'wstalist',
    ];

    //Use SSH2 for connection
    public function exec_cmds($cmds, $timeout = null)
    {
        return $this->exec_cmds_2($cmds, $timeout);
    }

    /*
    This method is used to establish a CLI session with a device.
    It will attempt to use phpseclib\Net\SSH2 library to connect.
    Returns a phpseclib\Net\SSH2 object.
    */
/*     public function getCli($timeout = 20)
    {
        $credentials = $this->getCredentials();
        foreach ($credentials as $credential) {
            // Attempt to connect using Metaclassing\SSH library.
            try {
                $cli = $this->getSSH2($this->ip, $credential->username, $credential->passkey);
            } catch (\Exception $e) {
                //If that fails, attempt to connect using phpseclib\Net\SSH2 library.
            }
            if ($cli) {
                $this->credential_id = $credential->id;
                //$this->save();
                //$cli->exec("sudo -i");
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
