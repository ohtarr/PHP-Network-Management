<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\VirtualChassis;
use App\Models\Netbox\IPAM\IpAddresses;
use App\Models\Netbox\VIRTUALIZATION\VirtualMachines;
use App\Models\Gizmo\DNS\A;
use App\Models\Gizmo\DNS\Cname;

class syncDns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netman:syncDns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync DNS records from netbox to DNS';

    /**
     * Execute the console command.
     *
     * @return int
     */

    protected $arecords;
    protected $cnamerecords;
    protected $generated;

    public function handle()
    {
        //print_r($this->generateAllRecords2());
        //return null;
        $this->deleteRecords();
        $this->fixRecords();
        $this->addRecords();
        //print_r($this->recordsToAdd());
        //$this->recordsToDelete();
        //$this->recordsToFix();
    }

    public function getARecords($fresh = false)
    {
        if($fresh)
        {
            $this->arecords = null;
        }
        if(!$this->arecords)
        {
            $this->arecords = A::all();
        }
        print "Fetched " . count($this->arecords) . " A records." . PHP_EOL;
        return $this->arecords;
    }

    public function getCnameRecords($fresh = false)
    {
        if($fresh)
        {
            $this->cnamerecords = null;
        }
        if(!$this->cnamerecords)
        {
            $this->cnamerecords = Cname::all();
        }
        print "Fetched " . count($this->cnamerecords) . " CNAME records." . PHP_EOL;
        return $this->cnamerecords;
    }

    public function generateAllRecords3()
    {
        if(!$this->generated)
        {
            //$devices = Devices::all();
            $devices = Devices::where('virtual_chassis_member', 'false')->where('name__empty','false')->where('limit','9999')->get();
            foreach($devices as $device)
            {
                unset($devicedns);

                print "Generating DNS for device {$device->name}..." . PHP_EOL;
                $devicedns = $device->generateDnsNames();
                foreach($devicedns as $record)
                {
                    $records[] = $record;
                }
            }
            $vcs = VirtualChassis::where('limit','1000')->get();
            foreach($vcs as $vc)
            {
                unset($vcdns);
                print "Generating DNS for virtual chassis {$vc->name}..." . PHP_EOL;
                $vcdns = $vc->generateDnsNames();
                foreach($vcdns as $record)
                {
                    $records[] = $record;
                }
            }
            $vms = VirtualMachines::where('limit','1000')->get();
            foreach($vms as $vm)
            {
                unset($vmdns);
                print "Generating DNS for virtual Machine {$vm->name}..." . PHP_EOL;
                $vmdns = $vm->generateDnsNames();
                foreach($vmdns as $record)
                {
                    $records[] = $record;
                }
            }            
            $this->generated = $records;
        }
        print "Generated " . count($this->generated) . " records from Netbox." . PHP_EOL;
        return $this->generated;
    }

    public function recordsToDelete()
    {
        $delete = [];
        $arecords = $this->getARecords();
        //print "A records : " . $arecords->count() . PHP_EOL;
        //$cnames = $this->getCnameRecords();
        //print "CNAME records : " . $cnames->count() . PHP_EOL;
        //$merged = $cnames->merge($arecords);
        //print "MERGED records : " . $merged->count() . PHP_EOL;
        $generated = $this->generateAllRecords();
        //print "GENERATED records : " . count($generated) . PHP_EOL;
//        print_r($generated);
        foreach($arecords as $record)
        {
            $match = 0;
            foreach($generated as $grecord)
            {
                //print "{$record->hostName} & {$record->recordType} == {$grecord['hostname']} & {$grecord['type']} ??" . PHP_EOL;
                if(strtolower($grecord['hostname']) == strtolower($record->hostName) && strtolower($grecord['type']) == strtolower($record->recordType))
                {
                    $match = 1;
                    break;
                }
            }
            if($match == 0)
            {
                $delete[] = $record;
            }
        }
        print "DELETE records : " . count($delete) . PHP_EOL;
        return $delete;
    }

    public function deleteRecords()
    {
        print "*** Deleting DNS records ***" . PHP_EOL;
        $delete = $this->recordsToDelete();
        print count($delete) . " records to delete..." . PHP_EOL;
        foreach($delete as $record)
        {
            try {
                print "### deleting record {$record->hostName}... ###" . PHP_EOL;
                $record->delete();
            } catch (\Exception $e) {
                print "Error occurred: " . $e->getMessage() . PHP_EOL;
            }
        }
    }

    public function recordsToAdd()
    {
        $add = [];
        $generated = $this->generateAllRecords();
        $arecords = $this->getARecords(true);
        //$cnames = $this->getCnameRecords(true);
        //$merged = $arecords->merge($cnames);
        foreach($generated as $grecord)
        {
            $match = 0;
            foreach($arecords as $record)
            {
                if(strtolower($grecord['hostname']) == strtolower($record->hostName) && strtolower($grecord['type']) == strtolower($record->recordType))
                {
                    $match = 1;
                    break;
                }
            }
            if($match == 0)
            {
                $add[] = $grecord;
            }
        }
        print "ADD records : " . count($add) . PHP_EOL;
        return $add;
    }

    public function addRecords()
    {
        print "*** Adding DNS records ***" . PHP_EOL;
        $typemap = [
            'a'     =>  A::class,
            'cname' =>  Cname::class,
        ];

        $add = $this->recordsToAdd();
        print count($add) . " records to add..." . PHP_EOL;
        foreach($add as $record)
        {
            $results = null;
            print "### record {$record['hostname']} {$record['data']} ###" . PHP_EOL;
            $classtype = null;
            foreach($typemap as $key => $value)
            {
                if($record['type'] == $key)
                {
                    $classtype = $value;
                    print "detected class type {$classtype}" . PHP_EOL;
                    break;
                }
            }
            if(!$classtype)
            {
                print "Unable to determine class type, skipping!" . PHP_EOL;
                continue;
            }
            
            try {
                print "Adding record {$record['hostname']} {$record['data']}" . PHP_EOL;
                $results = $classtype::create($record['hostname'], $record['data']);
            } catch (\Exception $e) {
                print "Error occurred: " . $e->getMessage() . PHP_EOL;
            }
            //print_r($results);
        }
    }

    public function recordsToFix()
    {
        $fix = [];
        //print "*** Checking existing DNS records ***" . PHP_EOL;
        $generated = $this->generateAllRecords();
        $arecords = $this->getARecords(true);
        //$cnames = $this->getCnameRecords(true);
        //$merged = $arecords->merge($cnames);

        foreach($arecords as $record)
        {
            //print "Checking host {$record->hostName} data {$record->recordData}..." . PHP_EOL;
            foreach($generated as $grecord)
            {
                if(strtolower($record->hostName) == strtolower($grecord['hostname']))
                {
                    //print "Found match, checking data..." . PHP_EOL;
                    if($record->recordData != $grecord['data'])
                    {
                        //print "record data does NOT match, deleting record..." . PHP_EOL;
                        $fix[] = $record;
                        //$record->delete();
                    } else {
                        //print "record data MATCHES, skipping..." . PHP_EOL;
                    }
                    break;
                }
            }
        }
        print "FIX records : " . count($fix) . PHP_EOL;
        return $fix;
    }

    public function fixRecords()
    {
        $records = $this->recordsToFix();
        print "*** Checking existing DNS records ***" . PHP_EOL;
        foreach($records as $record)
        {
            print "deleting record {$record->hostName} data {$record->recordData}..." . PHP_EOL;
            $record->delete();
        }
    }

    public function generateAllRecords()
    {
        if(!$this->generated)
        {
            $dns = [];
            $cidrreg = '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/';
            $ipreg = '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/';
            $devices = Devices::where('virtual_chassis_member', 'false')->where('name__empty','false')->where('limit','9999')->where('has_primary_ip','true')->get();
            foreach($devices as $device)
            {
                unset($ip);
                unset($dnsname);
                if(preg_match($cidrreg, $device->primary_ip->address, $hits))
                {
                    $ip = $hits[1];
                } else {
                    continue;
                }
                $dnsname = $device->generateDnsName();
                if($dnsname)
                {
                    $dns[$dnsname] = $ip;
                }
            }
            $oobdevices = Devices::where('virtual_chassis_member', 'false')->where('name__empty','false')->where('limit','9999')->where('has_oob_ip','true')->get();
            foreach($oobdevices as $device)
            {
                unset($ip);
                unset($dnsname);
                if(preg_match($cidrreg, $device->oob_ip->address, $hits))
                {
                    $ip = $hits[1];
                } else {
                    continue;
                }
                $dnsname = $device->generateDnsName();
                if($dnsname)
                {
                    $dns[$dnsname . "-oob"] = $ip;
                }
            }
            $noipdevices = Devices::where('virtual_chassis_member', 'false')->where('name__empty','false')->where('limit','9999')->where('has_primary_ip','false')->get();
            foreach($noipdevices as $device)
            {
                unset($ip);
                unset($dnsname);
                if(!isset($device->custom_fields->ip))
                {
                    continue;
                }
                if(preg_match($ipreg, $device->custom_fields->ip, $hits))
                {
                    $ip = $hits[1];
                } else {
                    continue;
                }
                $dnsname = $device->generateDnsName();
                if($dnsname)
                {
                    $dns[$dnsname] = $ip;
                }
            }
            $vcs = VirtualChassis::where('limit','9999')->get();
            foreach($vcs as $vc)
            {
                unset($ip);
                unset($dnsname);
                $device = $vc->getMaster();
                if(!isset($device->id))
                {
                    continue;
                }
                if(isset($device->primary_ip->address))
                {
                    if(preg_match($cidrreg, $device->primary_ip->address, $hits))
                    {
                        $ip = $hits[1];
                    }
                } elseif(isset($device->custom_fields->ip)) {
                    if(preg_match($ipreg, $device->custom_fields->ip, $hits))
                    {
                        $ip = $hits[1];
                    }
                }
                if(isset($ip))
                {
                    $dnsname = $device->generateDnsName();
                    if($dnsname)
                    {
                        $dns[$dnsname] = $ip;
                    }
                }

                unset($ip);
                if(isset($device->oob_ip->address))
                {
                    if(preg_match($cidrreg, $device->oob_ip->address, $hits))
                    {
                        $ip = $hits[1];
                    }
                }
                if(isset($ip))
                {
                    $dnsname = 'oob.' . $device->generateDnsName();
                    if($dnsname)
                    {
                        $dns[$dnsname] = $ip;
                    }
                }
            }

            $vms = VirtualMachines::where('limit','1000')->where('has_primary_ip','true')->get();
            foreach($vms as $device)
            {
                unset($ip);
                unset($dnsname);
                if(preg_match($cidrreg, $device->primary_ip->address, $hits))
                {
                    $ip = $hits[1];
                } else {
                    continue;
                }
                $dnsname = $device->generateDnsName();
                if($dnsname)
                {
                    $dns[$dnsname] = $ip;
                }
            }
            $vms = VirtualMachines::where('limit','1000')->where('has_primary_ip','false')->get();
            foreach($vms as $device)
            {
                unset($ip);
                unset($dnsname);
                if(!isset($device->custom_fields->ip))
                {
                    continue;
                }
                if(preg_match($ipreg, $device->custom_fields->ip, $hits))
                {
                    $ip = $hits[1];
                } else {
                    continue;
                }
                $dnsname = $device->generateDnsName();
                if($dnsname)
                {
                    $dns[$dnsname] = $ip;
                }
            }
            $ips = IpAddresses::where('assigned_to_interface','true')->get();
            foreach($ips as $ip)
            {
                unset($intname);
                unset($int);
                try {
                    $int = $ip->getInterface();
                } catch (\Exception $e) {
                    print "Error occurred: " . $e->getMessage() . PHP_EOL;
                }

                if(!isset($int->id))
                {
                    continue;
                }
                $intname = $int->generateDnsName();
                if(isset($intname))
                {
                    $dns[$intname] = $ip->cidr()['ip'];
                }
            }

            $records = [];
            foreach($dns as $name => $ip)
            {
                unset($tmp);
                $tmp['hostname']    = $name;
                $tmp['data']        = $ip;
                $tmp['type']        = 'a';
                $records[] = $tmp;
            }
            return $records;
        }
        print "Generated " . count($this->generated) . " records from Netbox." . PHP_EOL;
        return $this->generated;
    }

}
