<?php

namespace App\Models\Device\Opengear;

use phpseclib\Net\SSH2;

class Opengear extends \App\Models\Device\Device
{
    protected static $singleTableSubclasses = [
    ];

    protected static $singleTableType = __CLASS__;

    public static $cli_timeout = 120;

    //public $promptreg = '/\S*[\$|#]\s*\z/';
    public $promptreg = '/^\s*[\$|#]\s*$/';

    public $precli = [];

    //List of commands to run during a scan of this device.
    public $scan_cmds = [
        ''                  => 'sudo /etc/scripts/support_report.sh',
        'run'               => 'config -g config',
        'version'           => 'cat /etc/version',
        'serial'            => 'showserial',
        'support_report'    => 'cat /etc/config/support_report',
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
        $reg = "/config.system.name (\S+)/";
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
        $reg = "/(\S+)/";
        if (preg_match($reg, $this->data['serial'], $hits)) {
            return $hits[1];
        }
    }

    /*
    Find the model of this device from DATA.
    Returns string (device model).
    */
    public function getModel()
    {
        $reg = "/<model>(\S+)<\/model>/";
        if (preg_match($reg, $this->data['support_report'], $hits)) {
            return $hits[1];
        }
    }

}
