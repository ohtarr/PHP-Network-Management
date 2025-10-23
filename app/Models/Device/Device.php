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
        'id',
        'type',
        'ip',
        'credential_id',
        'data',
        'deleted_at',
        'created_at',
        'updated_at',
      ];

    protected $casts = [
        'data' => 'array',
    ];

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

    public static $cli_timeout = 20;

    public $promptreg = '/\S*[\$|#|>]\s*\z/';

    public $precli = [
        'term length 0',
        'set cli screen-length 0',
        'set cli screen-width 1024',
        'no paging',
    ];

    public $scan_cmds = [];

    public $discover_commands = [
        'sh version',
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
                //if($this->id)
                //{
                    $this->credential_id = $credential->id;
                    $this->save();
                //}
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

	public function exec_cmds($cmds, $timeout = null)
    {
        return $this->exec_cmds_1($cmds, $timeout);
    }

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
    Once recategorized, it will perform discover() again.
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
        if($this->id)
        {
            $device->id = $this->id;
        }
        if($this->netbox_id)
        {
            $device->netbox_id = $this->netbox_id;
        }
        //run discover again.
        $device = $device->getTypeObject();
        return $device;
    }

    public function getType()
    {
        $device = $this->getTypeObject();
        return $device::class;
    }

    public static function discoverNew($ip)
    {
        $device = new self;
        $device->ip = $ip;
        return $device->discover();
    }

    public function discover()
    {
        $device = new self;
        if($this->netbox_id)
        {
            $device->netbox_id = $this->netbox_id;
        }
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

    public function getScanCmdOutputs()
    {
        if($this->scan_cmds)
        {
            return $this->exec_cmds($this->scan_cmds);
        }
    }

    /*
    This method is used to SCAN the device to obtain all of the command line outputs that we care about.
    This also configures database indexes for NAME, SERIAL, and MODEL.
    returns null
    */
    public function scan()
    {
        if(!$this->id)
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

    public function scanold()
    {
        $device = $this->getOutput();
        $device->save();
        return $device;
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

    public function ping($timeout = 5)
	{
		$PING = new Ping($this->getIpAddress());
        $PING->setTimeout($timeout);
		$LATENCY = $PING->ping();
		if (!$LATENCY)
		{
			return false;
		}else{
			return $LATENCY;
		}
	}

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
        $nb = new NetboxDevice;
        if($this->netbox_id)
        {
            return $nb->where('id',$this->netbox_id)->first();
        }
    }

    public function getNetboxDeviceByName()
    {
        $nb = new NetboxDevice;
        return $nb->where('name__ic',$this->getName())->first();
    }

    public function getNetboxDevice()
    {
        $nbdevice = $this->getNetboxDeviceById();
        if(!$nbdevice)
        {
            $nbdevice = $this->getNetboxDeviceByName();
        }
        return $nbdevice;
    }

    public function getIpAddress()
    {
        $nbdevice = $this->getNetboxDevice();
        return $nbdevice->getIpAddress();
    }

}
