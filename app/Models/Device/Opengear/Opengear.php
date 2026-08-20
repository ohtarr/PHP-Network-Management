<?php

namespace App\Models\Device\Opengear;

use \GuzzleHttp\Client as GuzzleClient;

class Opengear extends \App\Models\Device\Device
{
    protected static $singleTableSubclasses = [
    ];

    protected static $singleTableType = __CLASS__;

    public $cli_timeout = 180;

    //public $promptreg = '/\S*[\$|#]\s*\z/';
    public $promptreg = '/^\s*[\$|#]\s*$/';

    public $precli = [];

    //List of outputs to collect during a scan of this device.
    public $scan_outputs = [
        'srscript'	    =>	[
            'method'	=>	'ssh',
            'input'		=>	'sudo /etc/scripts/support_report.sh',
            'timeout'	=>	180,
            'include'	=>	false,
        ],
        'run'		    =>	[
            'method'	=>	'ssh',
            'input'		=>	'config -g config',
            'timeout'	=>	5,
        ],
        'version'		=>	[
            'method'	=>	'ssh',
            'input'		=>	'cat /etc/version',
            'timeout'	=>	5,
        ],
        'serial'		=>	[
            'method'	=>	'ssh',
            'input'		=>	'showserial',
            'timeout'	=>	5,
        ],
        'support_report'=>	[
            'method'	=>	'sftp',
            'input'		=>	'/etc/config/support_report',
        ],
        'apidescription'=>	[
            'method'	=>	'callback',
            'input'		=>	'apiGetNodeDescription',
        ],
        'apiversion'	=>	[
            'method'	=>	'callback',
            'input'		=>	'apiGetSystemVersion',
        ],
        'apicellstats'	=>	[
            'method'	=>	'callback',
            'input'		=>	'apiGetCellStats',
        ],
        'apiserialports'=>	[
            'method'	=>	'callback',
            'input'		=>	'apiGetSerialPorts',
        ],
    ];

    protected $apiToken;

    /*
    Find the name of this device from DATA.
    Returns string (device name).
    */

    public function getName()
    {
        $run = $this->getLatestOutputs('run');
        if(!isset($run->data))
        {
            return null;
        }
        $reg = "/config.system.name (\S+)/";
        if (preg_match($reg, $run->data, $hits)) {
            return $hits[1];
        }
    }

    /*
    Find the serial of this device from DATA.
    Returns string (device serial).
    */
    public function getSerial()
    {
        $output = $this->getLatestOutputs('support_report');
        if(!isset($output->data))
        {
            return null;
        }
        $reg = "/Serial number\|\s+(\d+)/";
        if(preg_match($reg, $output->data, $hits))
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
        $output = $this->getLatestOutputs('support_report');
        if(!isset($output->data))
        {
            return null;
        }
        $reg = "/<model>(\S+)<\/model>/";
        if (preg_match($reg, $output->data, $hits)) {
            return $hits[1];
        }
        $reg = "/Model\|\s+(\S+)/";
        if (preg_match($reg, $output->data, $hits)) {
            return $hits[1];
        }
    }

    public function getIccid()
    {
        $output = $this->getLatestOutputs('support_report');
        if(!isset($output->data))
        {
            return null;
        }
        $reg = "/sim-iccid\s+(\d+)/";
        if (preg_match($reg, $output->data, $hits)) {
            return $hits[1];
        }
    }

    public function getImei()
    {
        $output = $this->getLatestOutputs('support_report');
        if(!isset($output->data))
        {
            return null;
        }
        $reg = "/imei\s+(\d+)/";
        if (preg_match($reg, $output->data, $hits)) {
            return $hits[1];
        }
    }

    public function getVersion()
    {
        $output = $this->getLatestOutputs('support_report');
        if(!isset($output->data))
        {
            return null;
        }
        $reg = "/OpenGear\/\S+\s+Version (\S+)/";
        if (preg_match($reg, $output->data, $hits)) {
            return $hits[1];
        }
    }

    public function getInterfaces()
    {
        $output = $this->getLatestOutputs('support_report');
        if(!isset($output->data))
        {
            return null;
        }
        $reg = "/(eth0|eth1|wwan0).*?txqueuelen/s";
        $macreg = "/HWaddr\s+(\S+)/";
        $ipreg = "/inet addr:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/";
        $maskreg = "/Mask:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/";
        preg_match_all($reg, $output->data, $hits, PREG_SET_ORDER);
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

    public function getGuzzleClient()
    {
        return new GuzzleClient([
            'base_uri'  =>   "https://" . $this->getIpAddress() . "/api/v1.7/",
            'headers'   =>  [
                'Accept'        => 'application/json',
                'Content-Type'  =>  'application/json',
                'Authorization' => 'Token ' . $this->getApiToken(),
            ],
            'verify' => false,
        ]);
    }

    public function getApiToken()
    {
        if(!$this->apiToken)
        {
            $body = [
                'username'  =>  env('OPENGEAR_USERNAME'),
                'password'  =>  env('OPENGEAR_PASSWORD'),
            ];
            $client = new GuzzleClient([
                'base_uri'  =>   "https://" . $this->getIpAddress() . "/api/v1.7/",
                'headers'   =>  [
                    'Accept'        => 'application/json',
                    'Content-Type'  =>  'application/json',
                ],
                'verify' => false,
            ]);
            $path = "sessions";
            $response = $client->request('post', $path, ['body' => json_encode($body)]);
            $body = $response->getBody()->getContents();
            $object = json_decode($body);
            if($object->state == "authenticated")
            {
                $this->apiToken = $object->session;
            }
        }
        return $this->apiToken;
    }

    public function apiGetNodeDescription()
    {
        $client = $this->getGuzzleClient();
        $path = "nodeDescription";
        $response = $client->request('get', $path);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }

    public function apiGetSystemVersion()
    {
        $client = $this->getGuzzleClient();
        $path = "system/version";
        $response = $client->request('get', $path);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }

    public function apiGetCellStats()
    {
        $client = $this->getGuzzleClient();
        $path = "interfaces/cellmodem/status";
        $response = $client->request('get', $path);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }

    public function apiGetSerialPorts()
    {
        $client = $this->getGuzzleClient();
        $path = "serialPorts";
        $response = $client->request('get', $path);
        $body = $response->getBody()->getContents();
        $object = json_decode($body);
        return $object;
    }
}
