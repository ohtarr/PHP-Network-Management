<?php

namespace App\Models\Device;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use phpseclib3\Net\SFTP;
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

class Device extends Model
{
    use SoftDeletes, SingleTableInheritanceTrait, HasRolesAndAbilities;

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
                $return[] = $value->first();
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

    public $cli_timeout = 20;

    public $promptreg = '/\S*[\$|#|>]\s*\z/';

    public $precli = [
        'term length 0',
        'set cli screen-length 0',
        'set cli screen-width 1024',
        'no paging',
        'terminal pager 0',
    ];

    public $scan_outputs = [];

    public $discover_commands = [
        'show version',
        'show inventory',
        'cat /etc/version',
        'cat /etc/board.info',
        'show chassis detail',
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

    public function getCurrentCredential()
    {
        if(!isset($this->credential_id))
        {
            return null;
        }
        return Credential::find($this->credential_id);
    }

    public function getCredentialCandidates()
    {
        //Find all credentials matching the CLASS of the device first.
        $classcreds = Credential::where('class', get_class($this))->get();
        //Find all credentials that are global (not class specific).
        $allcreds = Credential::whereNull('class')->get();
        return $classcreds->merge($allcreds);
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
    This method is used to attempt to detect usable credentials on the device.  If found, it will add it to the device object and save to DB.
    */
    public function discoverCredentials()
    {
        $ip = $this->getIpAddress();
        if (!$ip) {
            throw new \Exception('No IP address found for this device.');
        }
        $candidates = $this->getCredentialCandidates();
        if($candidates->isEmpty())
        {
            throw new \Exception('No credential candidates found.');
        }
        foreach ($candidates as $credential) {
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
    This method establishes an SFTP connection to the device using phpseclib3\Net\SFTP.
    It uses getCurrentCredential() to retrieve the assigned credential.
    Throws an Exception if no credential is assigned, no IP is found, or login fails.
    Returns a phpseclib3\Net\SFTP object on success.
    */
    public function getSftp($timeout = null)
    {
        if (!$timeout) {
            $timeout = $this->cli_timeout;
        }

        $credential = $this->getCurrentCredential();

        if (!$credential) {
            throw new \Exception('Unable to determine credential for this device.');
        }

        $ip = $this->getIpAddress();

        if (!$ip) {
            throw new \Exception('No IP address found for this device.');
        }

        $sftp = new SFTP($ip);

        if (!$sftp->login($credential->username, $credential->passkey)) {
            throw new \Exception('SFTP Login Failed.');
        }

        $sftp->setTimeout($timeout);
        return $sftp;
    }

    /*
    This method downloads the contents of a remote file via SFTP and returns it as a string.
    Returns the file contents as a string, or null if the file could not be retrieved.

    @param string $remotePath  The full path to the file on the remote device (e.g. '/etc/config/support_report')
    */
    public function sftpGetFile(string $remotePath): ?string
    {
        $sftp     = $this->getSftp();
        $contents = $sftp->get($remotePath);

        return ($contents === false) ? null : $contents;
    }

    /*
    This method is a launch point to different methods of executing commands.
    This allows overiding capabilities in different dependant models.
    */
	public function exec_cmds($cmds, $timeout = null)
    {
        return $this->exec_cmds_netmiko($cmds);
    }

    /*
    This method connects to a device using python netmiko script, executes a command, returns the output, and disconnects the SSH session.
    If netmiko_type is unknown, it will run getNetmikoType() to attempt to determine the type.
    */
    public function exec_cmd_netmiko($cmd, $timeout=null)
    {
        if(!$timeout)
        {
            $timeout = $this->cli_timeout;
        }

        $credential = $this->getCurrentCredential();
        if(!$credential)
        {
            throw new \Exception('Unable to determine credential for this device.');
        }

        $ip = $this->getIpAddress();
        if(!$ip)
        {
            throw new \Exception('No IP address found for this device.');
        }

        $username = $credential->username;
        $password = $credential->passkey;

        if(!isset($this->data['netmiko_type']))
        {
            $detectedtype = $this->getNetmikoType();
            if(!$detectedtype)
            {
                throw new \Exception('Unable to determine netmiko type for this device.');
            }
            $type = $detectedtype;
        } else {
            $type = $this->data['netmiko_type'];
        }

        $exe = env('PYTHON_EXE');
        //$output = shell_exec("python3 bin/runcmd.py '{$ip}' '{$username}' '{$password}' '{$type}' '{$cmd}'");
        $cmd = "{$exe} bin/runcmd.py --host=\"{$ip}\" --username=\"{$username}\" --password=\"{$password}\" --type=\"{$type}\" --cmd=\"$cmd\" --timeout=\"$timeout\"";
        //print $cmd . PHP_EOL;
        $output = shell_exec($cmd);
        if($output)
        {
            $output = trim($output);
        }
        return $output;
    }

    /*
    This method takes an array of commands, executes all of them in a single SSH session, and returns
    the values as a key=>value array. All commands are batched into one runcmd.py call to minimise
    the number of SSH connections opened against the device.

    @param array    $cmds     Associative array of key => command string
    @param int|null $timeout  Optional read timeout in seconds; defaults to $this->cli_timeout
    */
    public function exec_cmds_netmiko($cmds, $timeout = null)
    {
        if (empty($cmds)) {
            return [];
        }

        $credential = $this->getCurrentCredential();
        if (!$credential) {
            throw new \Exception('Unable to determine credential for this device.');
        }

        $ip = $this->getIpAddress();
        if (!$ip) {
            throw new \Exception('No IP address found for this device.');
        }

        $username = $credential->username;
        $password = $credential->passkey;

        if (!isset($this->data['netmiko_type'])) {
            $detectedtype = $this->getNetmikoType();
            if (!$detectedtype) {
                throw new \Exception('Unable to determine netmiko type for this device.');
            }
            $type = $detectedtype;
        } else {
            $type = $this->data['netmiko_type'];
        }

        if (!$timeout) {
            $timeout = $this->cli_timeout;
        }

        $exe = env('PYTHON_EXE');

        // Build one --cmd argument per command so runcmd.py runs them all in a single SSH session
        $cmdArgs = '';
        foreach ($cmds as $cmd) {
            $cmdArgs .= " --cmd=\"{$cmd}\"";
        }

        $shellcmd = "{$exe} bin/runcmd.py --host=\"{$ip}\" --username=\"{$username}\" --password=\"{$password}\" --type=\"{$type}\"{$cmdArgs} --timeout=\"{$timeout}\"";
        $raw = shell_exec($shellcmd);

        $delimiter = '---NETMIKO_OUTPUT_DELIMITER---';
        $parts = $raw !== null ? explode($delimiter, $raw) : [];

        $output = [];
        $keys = array_keys($cmds);
        foreach ($keys as $i => $key) {
            $value = isset($parts[$i]) ? trim($parts[$i]) : null;
            $output[$key] = ($value !== '') ? $value : null;
        }

        return $output;
    }

    /*
    This method will utilize a python script that utilizes netmikos TYPE detection methods.  This returns the netmiko type, but does
    not modify the device object.
    */
    public function getNetmikoType()
    {
        $credential = $this->getCurrentCredential();
        if(!$credential)
        {
            throw new \Exception('Unable to determine credential for this device.');
        }
        $ip = $this->getIpAddress();
        if(!$ip)
        {
            throw new \Exception('No IP address found for this device.');
        }
        $username = $credential->username;
        $password = $credential->passkey;
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

    /*
    This method is utilizes the getType() method to determine what kind of device this is.  Once determined
    it updates the device in database.
    */
    public function discover()
    {
        $context = ['device_id' => $this->id, 'netbox_id' => $this->netbox_id];

        if(!$this->ping())
        {
            Log::warning("Device::discover() failed: device did not respond to ping.", $context);
            return null;
        }

        $credential = $this->getCurrentCredential();
        if(!$credential)
        {
            Log::info("Device::discover() no credential set, attempting discoverCredentials().", $context);
            $found = $this->discoverCredentials();
            if(!$found)
            {
                Log::warning("Device::discover() failed: no valid credentials found.", $context);
                return null;
            }
        }

        if(!isset($this->data['netmiko_type']))
        {
            Log::info("Device::discover() no netmiko_type set, attempting discoverNetmikoType().", $context);
            $this->discoverNetmikoType();
            if(!isset($this->data['netmiko_type']))
            {
                Log::warning("Device::discover() failed: could not determine netmiko type.", $context);
                return null;
            }
        }

        $device = new self($this->toArray());
        $type = $device->getType();
        if($type)
        {
            Log::info("Device::discover() identified device type as {$type}.", $context);
            if($this->id)
            {
                DB::table('devices')->where('id',$this->id)->update(['type' =>  $type]);
                return self::find($this->id);
            } else {
                $this->save();
                return self::find($this->id);
            }
        }

        Log::warning("Device::discover() failed: could not match device output to any known type.", $context);
        return null;
    }

    public function rediscover()
    {
        $this->credential_id = null;
        $data = $this->data;
        unset($data['netmiko_type']);
        $this->data = $data;

    }

    /*
    This method executes all scan_outputs for a device and returns the values.
    The outputs are NOT saved to the database.
    Supports 'ssh' and 'sftp' methods defined in $scan_outputs.
    Each entry may include an optional 'include' key (bool, default true).
    When 'include' => false, the command still executes but its output is excluded from the return value.

    SSH commands are batched into a single runcmd.py call (one SSH connection) using the maximum
    timeout defined across all SSH entries. SFTP commands are still executed individually.
    */
    public function getOutputs($type = null)
    {
        $output = [];
        if (!$this->scan_outputs) {
            return $output;
        }

        $outputs = $this->scan_outputs;

        // Filter to a specific type if requested
        if ($type) {
            $outputs = array_filter(
                $outputs,
                fn($key) => strtolower($key) === strtolower($type),
                ARRAY_FILTER_USE_KEY
            );
        }

        // Separate SSH and SFTP definitions
        $sshCommands = [];   // key => command string (for batching)
        $sshInclude  = [];   // key => bool
        $sshTimeouts = [];   // key => int|null
        $sftpDefs    = [];   // key => definition array

        foreach ($outputs as $key => $definition) {
            $method  = $definition['method']  ?? 'ssh';
            $input   = $definition['input']   ?? null;
            $include = $definition['include'] ?? true;
            $timeout = $definition['timeout'] ?? null;

            if (!$input) {
                continue;
            }

            if ($method === 'sftp') {
                $sftpDefs[$key] = $definition;
            } else {
                $sshCommands[$key] = $input;
                $sshInclude[$key]  = $include;
                $sshTimeouts[$key] = $timeout;
            }
        }

        // Batch all SSH commands into a single SSH connection using the max timeout
        if (!empty($sshCommands)) {
            $maxTimeout = $this->cli_timeout;
            foreach ($sshTimeouts as $t) {
                if ($t !== null && $t > $maxTimeout) {
                    $maxTimeout = $t;
                }
            }

            $sshResults = $this->exec_cmds_netmiko($sshCommands, $maxTimeout);

            foreach ($sshResults as $key => $result) {
                if ($sshInclude[$key] ?? true) {
                    $output[$key] = $result;
                }
            }
        }

        // Run SFTP commands individually (different protocol, cannot be batched)
        foreach ($sftpDefs as $key => $definition) {
            $include = $definition['include'] ?? true;
            $result  = $this->sftpGetFile($definition['input']);
            if ($include) {
                $output[$key] = $result;
            }
        }

        return $output;
    }

    /*
    Apply gitconfig line_filters to a raw output string.
    Strips blank/whitespace-only lines and any line matching a configured regex pattern
    from config/gitconfig.php. This normalises volatile lines (timestamps, checksums, etc.)
    so they do not cause spurious "changed" detections or pollute stored outputs.
    */
    public function applyLineFilters(string $output): string
    {
        $filters = config('gitconfig.line_filters', []);
        $lines = explode("\n", $output);
        $lines = array_filter($lines, function (string $line) use ($filters) {
            // Remove blank / whitespace-only lines
            if (trim($line) === '') {
                return false;
            }
            // Remove lines matching any filter pattern
            foreach ($filters as $pattern) {
                if (preg_match($pattern, $line)) {
                    return false;
                }
            }
            return true;
        });
        return implode("\n", $lines);
    }

    /*
    This method utilized the getOutputs method to obtain all of the command line outputs for the device and
    save them to the Outputs table.

    Behaviour is controlled by the DEVICE_OUTPUT_RETENTION env variable:
      - 0 (default / unset): delete all previous outputs of the same type before saving the new one
                             (original behaviour — only the latest record is kept).
      - N > 0              : keep the N most-recent outputs per device+type; oldest records are pruned
                             once the count exceeds N.

    Additionally:
      - Line filters from config/gitconfig.php are applied to the raw output before saving, stripping
        volatile lines (timestamps, checksums, etc.) that would otherwise cause false "changed" detections.
      - The filtered output is compared against the latest stored output; if they are identical the new
        record is NOT saved (and no pruning occurs), avoiding unnecessary DB writes.
    */
    public function scan($type = null)
    {
        if (!$this->id) {
            return null;
        }
        if (!$this->ping()) {
            return null;
        }

        $data      = $this->getOutputs($type);
        $retention = (int) env('DEVICE_OUTPUT_RETENTION', 0);

        foreach ($data as $key => $output) {
            if (!$output) {
                continue;
            }

            // Guard: if any output indicates a pending commit-confirmed rollback,
            // abort the entire scan — the device config is in a temporary state and
            // will revert automatically; saving it would produce a misleading record.
            if (stripos($output, 'commit confirmed will be rolled back') !== false) {
                Log::warning("Device::scan() aborted: 'commit confirmed will be rolled back' detected in '{$key}' output.", ['device_id' => $this->id]);
                return null;
            }

            // 1. Strip volatile / noisy lines using the shared line-filter config
            $filtered = $this->applyLineFilters($output);

            // 2. Skip saving if the filtered output is identical to the latest stored output
            $latest = $this->output()
                ->where('type', $key)
                ->orderBy('id', 'DESC')
                ->first();

            if ($latest && $latest->data === $filtered) {
                continue;
            }

            // 3. Save the new (filtered) output record
            $new             = new Output;
            $new->device_id  = $this->id;
            $new->type       = $key;
            $new->data       = $filtered;
            $new->save();

            // 4. Prune old records according to the retention setting
            if ($retention > 0) {
                // Keep only the N most-recent records; delete the oldest ones over the limit
                $count = $this->output()->where('type', $key)->count();
                if ($count > $retention) {
                    $deleteCount = $count - $retention;
                    $oldest = $this->output()
                        ->where('type', $key)
                        ->orderBy('id', 'ASC')
                        ->limit($deleteCount)
                        ->pluck('id');
                    Output::whereIn('id', $oldest)->delete();
                }
            } else {
                // Original behaviour: delete all previous outputs, keeping only the new one
                $this->output()
                    ->where('type', $key)
                    ->where('id', '!=', $new->id)
                    ->delete();
            }
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

    public function getMac()
    {
        
    }

    /*
    Expand a common abbreviated interface name to its full canonical form.
    This is useful when merging data from protocols that may use different
    abbreviation styles (e.g. CDP uses full names, LLDP may use short names).

    Handles the most common Cisco/vendor abbreviations. Returns the original
    string unchanged if no known prefix is matched.

    Examples:
      Gi0/1        -> GigabitEthernet0/1
      Fa0/1        -> FastEthernet0/1
      Te0/1        -> TenGigabitEthernet0/1
      Tw0/1        -> TwentyFiveGigE0/1
      Hu0/1        -> HundredGigE0/1
      Fo0/1        -> FortyGigabitEthernet0/1
      Et0/1        -> Ethernet0/1
      Ma0/0        -> Management0/0
      Se0/0        -> Serial0/0
      Tu0          -> Tunnel0
      Lo0          -> Loopback0
      Vl1          -> Vlan1
      Po1          -> Port-channel1
    */
    public function expandInterfaceName(string $name): string
    {
        $prefixMap = [
            'GigabitEthernet'        => ['Gi', 'Gig'],
            'FastEthernet'           => ['Fa', 'Fas'],
            'TenGigabitEthernet'     => ['Te', 'Ten'],
            'TwentyFiveGigE'         => ['Tw', 'Twe'],
            'FortyGigabitEthernet'   => ['Fo', 'For'],
            'HundredGigE'            => ['Hu', 'Hun'],
            'Ethernet'               => ['Et', 'Eth'],
            'Management'             => ['Ma', 'Mgm'],
            'Serial'                 => ['Se', 'Ser'],
            'Tunnel'                 => ['Tu', 'Tun'],
            'Loopback'               => ['Lo', 'Loo'],
            'Vlan'                   => ['Vl', 'Vla'],
            'Port-channel'           => ['Po', 'Por'],
            'AppGigabitEthernet'     => ['Ap', 'App'],
        ];

        foreach ($prefixMap as $full => $abbrevs) {
            foreach ($abbrevs as $abbrev) {
                // Match if the name starts with the abbreviation (case-insensitive)
                // followed immediately by a digit or slash (not more letters)
                if (preg_match('/^' . preg_quote($abbrev, '/') . '(\d.*)/i', $name, $m)) {
                    return $full . $m[1];
                }
            }
        }

        return $name;
    }

    /*
    Normalize a raw media-type string (as reported by a device's interface output) into one of
    four canonical values:
      - 'copper'    : RJ-45 / twisted-pair copper
      - 'fiber_sm'  : single-mode fiber (SFP-LX, SFP-ZX, SFP-LH, SFP-EX, SFP-LR, SFP-ER, SFP-ZR, etc.)
      - 'fiber_mm'  : multi-mode fiber (SFP-SX, SFP-SR, OM1/OM2/OM3/OM4, etc.)
      - 'fiber'     : fiber but mode cannot be determined from the keyword alone

    Returns null if the keyword does not match any known pattern.

    @param  string  $raw  The raw media-type value from the device (e.g. 'copper', 'SFP-LX', '10GBase-SR')
    @return string|null
    */
    public function normalizeMediaType(string $raw): ?string
    {
        $raw = strtolower(trim($raw));

        // ---- Copper keywords ----
        $copperPatterns = [
            '/copper/',
            '/rj.?45/',
            '/baset$/',         // 10BaseT, 100BaseT, 1000BaseT, 10GBaseT
            '/base.?t\b/',
            '/twisted.?pair/',
            '/\bsfp.?t\b/',     // SFP-T (copper SFP)
            '/\bsfp.?cu\b/',    // SFP-CU (direct-attach copper)
            '/\bdac\b/',        // Direct Attach Copper
            '/\btwinax/',
        ];

        // ---- Single-mode fiber keywords ----
        $smPatterns = [
            '/\bsm\b/',
            '/single.?mode/',
            '/\blx\b/',         // SFP-LX
            '/\blh\b/',         // SFP-LH
            '/\bex\b/',         // SFP-EX
            '/\bzx\b/',         // SFP-ZX
            '/\blr\b/',         // SFP-LR / 10GBase-LR
            '/\ber\b/',         // SFP-ER / 10GBase-ER
            '/\bzr\b/',         // 10GBase-ZR
            '/\blw\b/',         // 10GBase-LW
            '/\bew\b/',         // 10GBase-EW
            '/base.?lx/',
            '/base.?lr/',
            '/base.?er/',
            '/base.?zr/',
            '/base.?lh/',
            '/base.?ex/',
            '/base.?zx/',
            '/\bos[12]\b/',     // OS1 / OS2 single-mode fiber grades
        ];

        // ---- Multi-mode fiber keywords ----
        $mmPatterns = [
            '/\bmm\b/',
            '/multi.?mode/',
            '/\bsx\b/',         // SFP-SX
            '/\bsr\b/',         // SFP-SR / 10GBase-SR
            '/\bsw\b/',         // 10GBase-SW
            '/base.?sx/',
            '/base.?sr/',
            '/base.?sw/',
            '/\bom[1-5]\b/',    // OM1 / OM2 / OM3 / OM4 / OM5 multi-mode fiber grades
        ];

        // ---- Generic fiber keywords (mode unknown) ----
        $fiberPatterns = [
            '/fiber/',
            '/fibre/',
            '/optical/',
            '/\bsfp\b/',        // bare "SFP" with no mode indicator
            '/\bsfp\+/',        // SFP+
            '/\bqsfp/',         // QSFP / QSFP+
            '/\bxfp\b/',
            '/\bcfp\b/',
            '/\bglc\b/',        // GLC-* Cisco optic part numbers
        ];

        foreach ($copperPatterns as $pattern) {
            if (preg_match($pattern, $raw)) {
                return 'copper';
            }
        }
        foreach ($smPatterns as $pattern) {
            if (preg_match($pattern, $raw)) {
                return 'fiber_sm';
            }
        }
        foreach ($mmPatterns as $pattern) {
            if (preg_match($pattern, $raw)) {
                return 'fiber_mm';
            }
        }
        foreach ($fiberPatterns as $pattern) {
            if (preg_match($pattern, $raw)) {
                return 'fiber';
            }
        }

        return null;
    }

    /*
    This method is designed to be overwritten by subclasses.
    Returns an array of neighbor devices discovered via neighbor protocols (CDP, LLDP, etc.).
    Each neighbor is an associative array containing any of the following keys (omitted when null/empty):
      - name        : the neighbor device hostname
      - mac         : the neighbor device MAC address (lowercase, no separators)
      - local_port  : the local interface name facing the neighbor
      - remote_port : the remote interface name on the neighbor device
      - media_type  : the physical media type (e.g. 'copper', 'fiber')
    */
    public function getNeighbors(): array
    {
        return [];
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

    //NETBOX RELATIONSHIPS
    public function getNetboxDeviceById()
    {
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
