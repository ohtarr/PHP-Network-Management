<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Gizmo\Dhcp;
use App\Models\Mist\Device;

#[\AllowDynamicProperties]
class VirtualChassis extends BaseModel
{
    protected $app = "dcim";
    protected $model = "virtual-chassis";

    public function devices()
    {
        return Devices::where('virtual_chassis_id',$this->id)->get();
    }

    public function getMaster()
    {
        if(isset($this->master->id))
        {
            return Devices::find($this->master->id);
        }
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
        $scope = Dhcp::findScopeByIp($ip);
        if(!(isset($scope) && $scope))
        {
            return null;
        }
        return [
            'scopeId'   =>  $scope->scopeID,
            'clientId'  =>  $dhcpid,
            'ipAddress' =>  $ip,
            'description'   =>  "NETMAN-" . $this->name,
        ];
    }

    public function getMistVirtualChassis()
    {
        return Device::findByName($this->name);
    }

}