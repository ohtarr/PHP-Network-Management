<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Dhcp\SubnetV4;
use App\Models\Mist\Device;

#[\AllowDynamicProperties]
class VirtualChassis extends BaseModel
{
    protected $app = "dcim";
    protected $model = "virtual-chassis";

    protected $cachedMaster = null;

    public function devices()
    {
        return Devices::where('virtual_chassis_id',$this->id)->get();
    }

    public function getMaster()
    {
        if (!$this->cachedMaster && isset($this->master->id)) {
            $this->cachedMaster = Devices::find($this->master->id);
        }
        return $this->cachedMaster;
    }

    public function getIpAddress()
    {
        $master = $this->getMaster();
        if(isset($master) && $master)
        {
            return $master->getIpAddress();
        }
    }

    public function generateDnsName()
    {
        $newname = str_replace("/","-",$this->name);
        $newname = str_replace(".","-",$newname);
        $newname = strtolower($newname);
        return $newname;        
    }

    public function generateDnsNames()
    {
        $dnsrecords = [];
        $ip = $this->getIpAddress();
        if(!$ip)
        {
            return $dnsrecords;
        }
        $dnsrecords[] = [
            'hostname'  =>  $this->generateDnsName(),
            'data'      =>  $ip,
            'type'      =>  'a',
        ];
        return $dnsrecords;
    }

    public function generateDhcpId($irb = 0)
    {
        $master = $this->getMaster();
        if(isset($master) && $master)
        {
            return $master->generateDhcpId($irb);
        }
    }

    public function generateDhcpReservation()
    {
        $master = $this->getMaster();
        if(!isset($master->id))
        {
            return null;
        }
        $dhcpid = $master->generateDhcpId();
        if(!(isset($dhcpid) && $dhcpid))
        {
            return null;
        }
        $ip = $master->getIpAddress();
        if(!(isset($ip) && $ip))
        {
            return null;
        }
        return [
            'ipaddress'   =>  $ip,
            'hwaddress'   =>  $dhcpid,
            'description' =>  "NETMAN-" . $this->name,
        ];
    }

    public function getMistVirtualChassis()
    {
        return Device::findByName($this->name);
    }

}