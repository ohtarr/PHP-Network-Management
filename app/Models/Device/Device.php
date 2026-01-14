<?php

namespace App\Models\Device;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use phpseclib3\Net\SSH2;
use App\Models\Credential\Credential;
use Nanigans\SingleTableInheritance\SingleTableInheritanceTrait;
use App\Models\Device\DeviceCollection as Collection;
use Silber\Bouncer\Database\HasRolesAndAbilities;
use JJG\Ping;
use App\Models\Device\Output;
use App\Models\Device\Aruba\Aruba;
use App\Models\Device\Cisco\Cisco;
use App\Models\Device\Opengear\Opengear;
use App\Models\Device\Ubiquiti\Ubiquiti;
use App\Models\Device\Juniper\Juniper;
use App\Models\ServiceNow\Location;
use App\Models\Netbox\DCIM\Devices as NetboxDevice;

class Device extends Model
{
    //use Searchable;	// Add for Scout to search */
    use SoftDeletes, SingleTableInheritanceTrait, HasRolesAndAbilities;
    //use SingleTableInheritanceTrait, HasRolesAndAbilities;


    protected $table = 'devices';
    protected static $singleTableTypeField = 'type';
    protected static $singleTableSubclasses = [
        Aruba::class,
        Cisco::class,
        Opengear::class,
        Ubiquiti::class,
        Juniper::class,
    ];
    protected static $singleTableType = __CLASS__;

    protected $fillable = [
        'type',
        'netbox_type',
        'netbox_id',
        'credential_id',
        'data',
      ];

    protected $casts = [
        'data' => 'array',
    ];

    protected $nbdevice;

    public function output()
    {
        return $this->hasMany(Output::class,'device_id');
    }

    public function getAllOutputs($type = null)
    {
        if($type)
        {
            return $this->output()->where('type',$type)->get();
        } else {
            return $this->output;
        }
    }

    public function getLatestOutputs($type = null)
    {
        if($type)
        {
            return $this->output()->where('type',$type)->orderBy('id', 'DESC')->first();
        } else {
            $return = [];
            $outputs = $this->output()->orderBy('id', 'DESC')->get();
            $grouped = $outputs->groupBy('type');
            foreach($grouped as $key => $value)
            {
                $return[$key] = $value->first();
            }
            return collect($return);
        }
    }

    public function getLastScanTime()
    {
        $output = $this->getLatestOutputs()->first();
        if(isset($output->created_at))
        {
            return $output->created_at;
        }
    }

    public static $cli_timeout = 20;

    public $promptreg = '/\S*[\$|#|>]\s*\z/';

    public $precli = [
        'term length 0',
        'set cli screen-length 0',
        'set cli screen-width 1024',
        'no paging',
        'terminal pager 0',
    ];

    public $scan_cmds = [];

    public $discover_commands = [
        'show version',
        'show inventory',
        'cat /etc/version',
        'cat /etc/board.info',
    ];

    public $discover_regex = [
        Aruba::class   => [
            '/Aruba/i',
        ],
        Cisco::class     => [
            '/Cisco/i',
        ],
        Opengear::class   => [
            '/Opengear/i',
        ],
        Ubiquiti::class   => [
            '/NBE-5AC/i',
        ],
        Juniper::class   => [
            '/JUNOS/i',
            '/Junos/i',
        ],

    ];

    public $parser = null;
    
    public $parsed = null;

    public function newCollection(array $models = [])
    { 
       return new Collection($models);
    }

    public function scopeSelectData($query, $data)
    {
        return $query->addselect('data->' . $data . ' as ' . $data);
    }

/*     public static function getColumns()
    {
        return self::$columns;
    } */

    public function credential()
    {
        return $this->belongsTo(Credential::class, 'credential_id', 'id');
    }

    /*
    This method is used to generate a COLLECTION of credentials to use to connect to this device.
    Returns a COLLECTION
    */
    public function getCredentials()
    {
        if ($this->credential) {
            //If the device already has a credential assigned for use, return it in a collection.
            return collect([$this->credential]);
        } else {
            //Find all credentials matching the CLASS of the device first.
            $classcreds = Credential::where('class', get_class($this))->get();
            //Find all credentials that are global (not class specific).
            $allcreds = Credential::whereNull('class')->get();
        }
        //Return a collection of credentials to attempt.
        return $classcreds->merge($allcreds);
    }
    /*
    public function discoverCredentials()
    {
        $credentials = $this->getCredentials();
        if(!$credentials)
        {
            return null;
        }
        foreach ($credentials as $credential) {
            //Attempt to connect using phpseclib\Net\SSH2 library.
            try {
                $cli = $this->getSSH2($this->getIpAddress(), $credential->username, $credential->passkey, 20);
            } catch (\Exception $e) {
                echo $e->getMessage()."\n";
            }

            if (isset($cli))
            {
                $this->credential_id = $credential->id;
                $this->save();
                return $credential;
            }
        }
    }
    /**/

    /*
    This method is used to attempt to detect usable credentials on the device.  If found, it will add it to the device object and save to DB.
    */
    public function discoverCredentials()
    {
        $ip = $this->getIpAddress();
        $credentials = $this->getCredentials();
        if(!$credentials)
        {
            return null;
        }
        foreach ($credentials as $credential) {
            //Attempt to connect using phpseclib\Net\SSH2 library.
            try {
                $exe = env('PYTHON_EXE');
                $cmd = "{$exe} bin/testcreds.py --host=\"{$ip}\" --username=\"{$credential->username}\" --password=\"{$credential->passkey}\"";
                //print $cmd . PHP_EOL;
                $output = intval(shell_exec($cmd));
            } catch (\Exception $e) {
                echo $e->getMessage()."\n";
            }
            if($output)
            {
                $this->credential_id = $credential->id;
                $this->save();
                return $credential;
            }
        }
    }

    /*
    This method is used to establish a CLI session with a device.
    It will attempt to use Metaclassing\SSH library to work with specific models of devices that do not support ssh2.0 natively.
    If it fails to establish a working SSH session with Metaclassing\SSH, it will then attempt using phpseclib\Net\SSH2.
    Returns a Metaclassing\SSH object OR a phpseclib\Net\SSH2 object.
    */
    public function getCli($timeout = null)
    {
        if(!$timeout)
        {
            $timeout = $this->cli_timeout;
        }
        //Get our collection of credentials to attempt and foreach them.
        $credentials = $this->getCredentials();
        if(!$credentials)
        {
			throw new \Exception('No Credentials found!');
        }
        foreach ($credentials as $credential) {
            //Attemp to connect using phpseclib\Net\SSH2 library.
            try {
                $cli = $this->getSSH2($this->getIpAddress(), $credential->username, $credential->passkey, $timeout);
            } catch (\Exception $e) {
                echo $e->getMessage()."\n";
            }

            if (isset($cli)) {
                return $cli;
            }
        }
    }

	public function getSSH2($ip, $username, $password, $timeout = 20)
	{
		$cli = new SSH2($ip);
		
        if (!$cli->login($username, $password)) {
			throw new \Exception('Login Failed');
        }
        $cli->setTimeout($timeout);
        $OUTPUT = $cli->read($this->promptreg , SSH2::READ_REGEX);
        foreach($this->precli as $precli)
        {
            $cli->write($precli . "\n");
            $cli->read($this->promptreg , SSH2::READ_REGEX);            
        }
		return $cli;
	}

    /*
    This method is used to attempt an SSH V1 terminal connection to the device.
    It will attempt to use Metaclassing\SSH library to work with specific models of devices that do not support ssh 2.0 natively.
    If it successfully connects and detects prompt, it will return a CLI handle.
    */
/*     public static function getSSH1($ip, $username, $password)
    {
        $deviceinfo = [
            'host'      => $ip,
            'username'  => $username,
            'password'  => $password,
        ];
        $cli = new SSH($deviceinfo);
        $cli->connect();
        if ($cli->connected) {
            // send the term len 0 command to stop paging output with ---more---
            $cli->exec('terminal length 0');  //Cisco
            $cli->exec('no paging');  //Aruba
            return $cli;
        }
    } */

    /*
    This method is a launch point to different methods of executing commands.
    This allows overiding capabilities in different dependant models.
    */
	public function exec_cmds($cmds, $timeout = null)
    {
        return $this->exec_cmds_netmiko($cmds);
    }
    /*
	public function exec_cmds_1($cmds, $timeout = null)
	{
        if(!$timeout)
        {
            $timeout = $this->cli_timeout;
        }
		$cli = $this->getCli($timeout);
        if(!$cli)
        {
			throw new \Exception('Unable to establish CLI!');
        }
		if(is_array($cmds))
		{
			foreach($cmds as $key => $cmd)
			{
				$cli->write($cmd . "\n");
				$output[$key] = $cli->read($this->promptreg , SSH2::READ_REGEX);
			}
		} elseif (is_string($cmds)) {
			$LINES = explode("\n", $cmds);
			$output = "";
			foreach($LINES as $LINE)
			{
				if(!$LINE)
				{
					continue;
				}
				$cli->write($LINE . "\n");
				$output .= $cli->read($this->promptreg , SSH2::READ_REGEX);
			}
		}
		$cli->disconnect();
		return $output;
	}

	public function exec_cmds_2($cmds, $timeout = null)
	{
		$cli = $this->getCli();
        if(!$cli)
        {
			throw new \Exception('Unable to establish CLI!');
        }
        if(is_array($cmds))
		{
			foreach($cmds as $key => $cmd)
			{
				$output[$key] = $cli->exec($cmd);
			}
		} elseif (is_string($cmds)) {
			$LINES = explode("\n", $cmds);
			$output = "";
			foreach($LINES as $LINE)
			{
				if(!$LINE)
				{
					continue;
				}
				$output .= $cli->exec($LINE);
			}
		}
		$cli->disconnect();
		return $output;
	}
    /**/
    /*
    This method connects to a device using python netmiko script, executes a command, returns the output, and disconnects the SSH session.
    If netmiko_type is unknown, it will run getNetmikoType() to attempt to determine the type.
    */
    public function exec_cmd_netmiko($cmd)
    {
        $ip = $this->getIpAddress();
        if(!isset($ip))
        {
            return null;
        }

        if(!isset($this->credential))
        {
            return null;
        }
        $username = $this->credential['username'];
        $password = $this->credential['passkey'];

        if(!isset($this->data['netmiko_type']))
        {
            $detectedtype = $this->getNetmikoType();
            if(!$detectedtype)
            {
                return null;
            }
            $type = $detectedtype;
        } else {
            $type = $this->data['netmiko_type'];
        }

        $exe = env('PYTHON_EXE');
        //$output = shell_exec("python3 bin/runcmd.py '{$ip}' '{$username}' '{$password}' '{$type}' '{$cmd}'");
        $cmd = "{$exe} bin/runcmd.py --host=\"{$ip}\" --username=\"{$username}\" --password=\"{$password}\" --type=\"{$type}\" --cmd=\"$cmd\"";
        //print $cmd . PHP_EOL;
        $output = shell_exec($cmd);
        if($output)
        {
            $output = trim($output);
        }
        return $output;
    }

    /*
    This method takes an array of commands, executes each of them, and returns the values as a key=>value array.
    */
    public function exec_cmds_netmiko($cmds)
    {
        $output = [];
        foreach($cmds as $key => $cmd)
        {
            $output[$key] = $this->exec_cmd_netmiko($cmd);
        }
        return $output;
    }

    /*
    This method will utilize a python script that utilizes netmikos TYPE detection methods.  This returns the netmiko type, but does
    not modify the device object.
    */
    public function getNetmikoType()
    {
        $ip = $this->getIpAddress();
        if(!isset($this->credential))
        {
            return null;
        }
        $username = $this->credential['username'];
        $password = $this->credential['passkey'];
        $exe = env('PYTHON_EXE');
        $cmd = "{$exe} bin/detecttype.py --host=\"{$ip}\" --username=\"{$username}\" --password=\"{$password}\"";
        //print $cmd . PHP_EOL;
        $output = shell_exec($cmd);
        $type = trim($output);
        return $type;
    }

    /*
    This method runs the getNetmikoType method, and saves the netmiko_type to the device object for future use.
    */
    public function discoverNetmikoType()
    {
        $type = $this->getNetmikoType();
        if(!$type || $type == "None")
        {
            return null;
        }
        $data = $this->data;
        $data['netmiko_type'] = $type;
        $this->data = $data;
        $this->save();
        return $this;
    }

    /*
    This method is used to attempt an SSH V2 terminal connection to the device.
    It will utilize the phpseclib\net\SSH library and return a CLI handle if successful
    */
/*     public static function getSSH2($ip, $username, $password)
    {
        //Try using phpseclib\Net\SSH2 to connect to device.
        $cli = new SSH2($ip);
        if ($cli->login($username, $password)) {
            return $cli;
        }
    } */

    /*
    This method is used to determine the TYPE of device this is and recategorize it.
    Once recategorized, it will perform discover() again until it no longer has any further options.
    Returns null;
    */
    public function getTypeObject()
    {
        //print "getType()\n";
        //print "GETTYPE THIS ID: {$this->id}\n";
        /*
        If an ip doesn't exist on this object you are trying to discover, fail
        Check if a device with this IP already exists.  If it does, grab it from the database and perform a discovery on it
        */
        $ip = $this->getIpAddress();
        if(!$ip)
        {
            print "No IP address found!\n";
            return false;
        }

        echo get_called_class()."\n";

        if(empty(static::$singleTableSubclasses))
        {
            //return $this->post_discover();
            return $this;
        }

        /*
        This goes through each $discover_regex defined above and builds (1) array:
        $match = an array of classes and how many MATCHES we have (starts at 0 for each)
        Example:
            Array
            (
                [App\Device\Aruba\Aruba] => 0
                [App\Device\Cisco\Cisco] => 0
                [App\Device\Opengear\Opengear] => 0
            )
        */
        foreach(static::$singleTableSubclasses as $class)
        {
            $match[$class] = 0;
        }

        $outputs = $this->exec_cmds($this->discover_commands);

        foreach($outputs as $output)
        {
            if(!$output)
            {
                continue;
            }
            foreach ($this->discover_regex as $class => $regs)
            {
                foreach($regs as $reg)
                {
                    if (preg_match($reg, $output))
                    {
                        $match[$class]++;
                    }
                }
            }
        }

        //sort the $match array so the class with the highest count is on top.
        arsort($match);
        foreach($match as $key => $value)
        {
            $newtype = $key;
            //If there is no matches found, device cannot be discovered!
            if($value === 0)
            {
                return null;
            }
            break;
        }

        //Create a new model instance of type $newtype
        $device = $newtype::make($this->toArray());
        /*
        if($this->id)
        {
            $device->id = $this->id;
        }
        if($this->netbox_type)
        {
            $device->netbox_type = $this->netbox_type;
        }
        if($this->netbox_id)
        {
            $device->netbox_id = $this->netbox_id;
        }
        if($this->credential_id)
        {
            $device->credential_id = $this->credential_id;
        }
        /**/
        //run discover again.
        $device = $device->getTypeObject();
        return $device;
    }

    /*
    This method runs the getTypeObject method, and returns the string name of the object type.
    */
    public function getType()
    {
        $device = $this->getTypeObject();
        if($device)
        {
            return $device::class;
        }
    }

/*     public static function discoverNew($ip)
    {
        $device = new self;
        $device->ip = $ip;
        return $device->discover();
    } */

    /*
    This method is utilizes the getType() method to determine what kind of device this is.  Once determined
    it updates the device in database.
    */
    public function discover()
    {
        if(!$this->ping())
        {
            return null;
        }
        if(!isset($this->credential))
        {
            $this->discoverCredentials();
        }
        if(!isset($this->data['netmiko_type']))
        {
            $this->discoverNetmikoType();
        }

        /*
        $device = new self;
        if($this->netbox_id)
        {
            $device->netbox_id = $this->netbox_id;
        }
        if($this->netbox_type)
        {
            $device->netbox_type = $this->netbox_type;
        }
        if($this->credential_id)
        {
            $device->credential_id = $this->credential_id;
        } else {
            $cred = $this->discoverCredentials();
            if($cred)
            {
                $device->credential_id = $cred->id;
            }
        }
        /**/
        $device = new self($this->toArray());
        $type = $device->getType();
        if($type)
        {
            if($this->id)
            {
                DB::table('devices')->where('id',$this->id)->update(['type' =>  $type]);
                return self::find($this->id);
            } else {
                $this->save();
                return self::find($this->id);
            }
        }
    }

    /*
    This method is used to determine if this devices IP is already in the database.
    Returns null;
    */
/*     public function deviceExists()
    {
        print "deviceExists()\n";
        $exists = $this->where('netbox_id',$this->netbox_id)->whereNot('id',$this->id)->get();
        if($exists->isNotEmpty())
        {
            return $exists;
        } else {
            return null;
        }
        //print_r($this);
        //$this->getOutput();
         $device = Device::where('ip',$this->ip)
            ->orWhere("serial", $this->serial)
            ->orWhere("name", $this->name)
            ->first();

        $device1 = self::where('ip',$this->ip)->withTrashed()->first();
        if($device1)
        {
            //print "IP MATCH!\n";
            return $device1;
        }
        if(isset($this->data['serial']))
        {
            $device2 = self::where("data->serial", $this->data['serial'])->withTrashed()->first();
            if($device2)
            {
                //print "SERIAL MATCH!\n";
                return $device2;
            }
        }
        if(isset($this->data['name']))
        {
            $device3 = self::where("data->name", $this->data['name'])->withTrashed()->first();
            if($device3)
            {
                //print "NAME MATCH!\n";
                return $device3;
            }
        }
        //print_r($device);
        //return $device;
    } */

    /*
    This method executes all scan_cmds for a device and returns the values
    The outputs are NOT saved to the database.
    */
    public function getScanCmdOutputs()
    {
        $output = [];
        if($this->scan_cmds)
        {
            $output = $this->exec_cmds($this->scan_cmds);
        }
        return $output;
    }

    /*
    This method utilized the getScanCmdOutputs method to obtain all of the command line outputs for the device and
    save them to the Outputs table.
    */
    public function scan()
    {
        if(!$this->id)
        {
            return null;
        }
        if(!$this->ping())
        {
            return null;
        }
        $data = $this->getScanCmdOutputs();
        
        foreach($data as $key => $output)
        {
            unset($existing);
            if(!$output)
            {
                continue;
            }
            $existing = $this->getAllOutputs($key);
            foreach($existing as $exists)
            {
                $exists->delete();
            }
            $new = new Output;
            $new->device_id = $this->id;
            $new->type = $key;
            $new->data = $output;
            $new->save();
        }
        return $this;
    }

    public function getName()
    {
    }

    public function getSerial()
    {
    }

    public function getModel()
    {
    }

    public function getMgmtIp()
    {

    }

    /*
    Perform simple ping of the device.
    */
    public function ping($timeout = 5)
	{
        $ip = $this->getIpAddress();
        if(!$ip)
        {
            return null;
        }
		$PING = new Ping($ip);
        $PING->setTimeout($timeout);
		$LATENCY = $PING->ping();
		if (!$LATENCY)
		{
			return false;
		}else{
			return $LATENCY;
		}
	}

    /*
    ping a device by ip.
    */
    public static function pingIp($ip, $timeout = 5)
    {
		$PING = new Ping($ip);
        $PING->setTimeout($timeout);
		$LATENCY = $PING->ping();
		if (!$LATENCY)
		{
			return false;
		}else{
			return $LATENCY;
		}
    }

    /*
    This method attempts to determine a devices public IP by telneting to "telnetmyip.com" and 
    returning the public ip detected.  This is desgigned to be overwritten on each dependant model
    for compatibility.
    */
    public function detectPublicIp()
    {
        $cmds = [
            'publicip'=>'telnet telnetmyip.com'
        ];
        $output = $this->exec_cmds($cmds);
        $telnetreg = "/\"ip\":\s+\"(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\"/";
        if(preg_match($telnetreg, $output['publicip'], $hits))
        {
            return $hits[1];
        }
    }

    public function parse(){
        $cp = new $this->parser("");
        foreach($this->data as $key=>$value){
            $cp->input_data($value,$key);
        }
        $this->parsed = $cp->output;
        return $this->parsed;
    }

    public function withoutData()
    {
        unset($this->data);
        return $this;
    }

    //SERVICE-NOW RELATIONSHIPS
    public function getSitecode()
    {
        return substr($this->getName(),0,8);
    }

    public function getServiceNowLocation()
    {
        return Location::where('name', $this->getSiteCode())->first();
    }

    //NETBOX RELATIONSHIPS
    public function getNetboxDeviceById()
    {
        //$nb = new NetboxDevice;
        if(!$this->netbox_type)
        {
            return null;
        }
        $nb = new $this->netbox_type;
        if($this->netbox_id)
        {
            return $nb->where('id',$this->netbox_id)->first();
        }
    }

    public function getNetboxDeviceByName()
    {
        $name = $this->getName();
        if(!$name)
        {
            return null;
        }
        //$nb = new NetboxDevice;
        if(!$this->netbox_type)
        {
            return null;
        }
        $nb = new $this->netbox_type;
        return $nb->where('name__ic', $this->getName())->first();
    }

    public function getNetboxDevice()
    {
        if(!$this->nbdevice)
        {
            $nbdevice = $this->getNetboxDeviceById();
            if(!$nbdevice)
            {
                $nbdevice = $this->getNetboxDeviceByName();
            }
            if($nbdevice)
            {
                $this->nbdevice = $nbdevice;
            }
        }
        return $this->nbdevice;
    }
    /*
    This method is designed to be used all over the place to acquire the IP of this device.
    This information is retreived from Netbox.
    */
    public function getIpAddress()
    {
        $nbdevice = $this->getNetboxDevice();
        if($nbdevice)
        {
            return $nbdevice->getIpAddress();
        }
    }

}
