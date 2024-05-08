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
        if(isset($this->data['serial']))
        {
            return $this->data['serial'];
        }

        $reg = "/Serial number\|\s+(\d+)/";
        if(preg_match($reg, $this->data['support_report'], $hits))
        {
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
        $reg = "/Model\|\s+(\S+)/";
        if (preg_match($reg, $this->data['support_report'], $hits)) {
            return $hits[1];
        }
    }

    public function getIccid()
    {
        $reg = "/sim-iccid\s+(\d+)/";
        if (preg_match($reg, $this->data['support_report'], $hits)) {
            return $hits[1];
        }
    }

    public function getImei()
    {
        $reg = "/imei\s+(\d+)/";
        if (preg_match($reg, $this->data['support_report'], $hits)) {
            return $hits[1];
        }
    }

    public function getVersion()
    {
        $reg = "/OpenGear\/\S+\s+Version (\S+)/";
        if (preg_match($reg, $this->data['support_report'], $hits)) {
            return $hits[1];
        }
    }

    public function getInterfaces()
    {
        $reg = "/(eth0|eth1|wwan0).*?txqueuelen/s";
        $macreg = "/HWaddr\s+(\S+)/";
        $ipreg = "/inet addr:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/";
        $maskreg = "/Mask:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/";
        preg_match_all($reg, $this->data['support_report'], $hits, PREG_SET_ORDER);
        $interfaces = [];
        foreach($hits as $interface)
        {
            $tmp = [];
            $tmp['name']  = $interface[1];
            if(preg_match($macreg, $interface[0], $machits))
            {
                $tmp['mac'] = $machits[1];
            }
            if(preg_match($ipreg, $interface[0], $iphits))
            {
                $tmp['ip'] = $iphits[1];
            }
            if(preg_match($maskreg, $interface[0], $maskhits))
            {
                $tmp['mask'] = $maskhits[1];
            }
            $interfaces[$interface[1]] = $tmp;
        }
        return $interfaces;
    }

    //Parse out the wired IP of eth0 from the support_report.  Returns a string.
    public function getWiredIp()
    {
        $intname = 'eth0';
        $interfaces = $this->getInterfaces();
        if(isset($interfaces[$intname]['ip']))
        {
            return $interfaces[$intname]['ip'];
        }
    }

    //Parse out the wireless IP of wwan0 from the support_report.  Returns a string.
    public function getWirelessIp()
    {
        $intname = 'wwan0';
        $interfaces = $this->getInterfaces();
        if(isset($interfaces[$intname]['ip']))
        {
            return $interfaces[$intname]['ip'];
        }
    }

    //Ping the wired IP and returns either FALSE or a float value of the latency.
    public function pingWiredIp()
    {
        return static::pingIp($this->getWiredIp());
    }

    //Ping the wireless IP and returns either FALSE or a float value of the latency.
    public function pingWirelessIp()
    {
        return static::pingIp($this->getWirelessIp());
    }

    public function getMgmtIp()
    {
        return $this->getWiredIp();
    }
}
