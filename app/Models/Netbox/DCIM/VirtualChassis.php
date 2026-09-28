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

/*     public function generateDhcpId($irb = 0)
    {
        $master = $this->getMaster();
        if(isset($master) && $master)
        {
            return $master->generateDhcpId($irb);
        }
    } */

/*     public function generateDhcpReservation()
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
    } */

    public function getMistVirtualChassis()
    {
        return Device::findByName($this->name);
    }

    public function generateKeaReservation()
    {
        //default irb interface to 0
        $irb = 0;
        //Code here to determine which IRB interface the device is using for mgmt. future...
        $option61 = false;
        $master = $this->getMaster();
        $ip = $master->getIpAddress();
        if(!$ip)
        {
            return null;
        }
        $dhcpid = null;
        //If dhcp_id is defined on netbox device, return it
        if(!$dhcpid && isset($master->custom_fields->dhcp_id))
        {
            $dhcpid = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $master->custom_fields->dhcp_id));
        }
        //If device is Juniper, rely on mist to determine dhcpid
        if(!$dhcpid && isset($master->device_type->manufacturer->name) && $master->device_type->manufacturer->name == "Juniper")
        {
            $mistdevice = $master->getMistDeviceBySerial();
            if(isset($mistdevice->id))
            {
                $dhcpid = $mistdevice->generateDhcpIdHex($irb);
                if($dhcpid)
                {
                    $option61 = true;
                }
            }
        }
        //Attempt to determine device MAC ADDRESS from the inventory tool.
        if(!$dhcpid)
        {
            $asset = $master->getSnipeitAsset();
            if(isset($asset->custom_fields->mac->value))
            {
                $dhcpid = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $asset->custom_fields->mac->value));
            }
        }
        //If all else fails, try to determine MAC ADDRESS from Device outputs.
        if(!$dhcpid)
        {
            $nmdevice = $master->getNetmanDevice();
            if(isset($nmdevice->id))
            {
                $dhcpid = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $nmdevice->getMac()));
            }
        }
        $description = "NETMAN-" . $this->name;
        $scope = SubnetV4::findByIp($ip);
        if(!isset($scope->id))
        {
            return null;
        }
        $params = [];
        $params['subnetId'] = $scope->id;
        if($option61)
        {
            $params['hwAddress'] = null;
            $params['clientId'] = $dhcpid;
        } else {
            $params['hwAddress'] = $dhcpid;
            $params['clientId'] = null;
        }
        $params['ipAddress'] = $ip;
        $params['usercontext'] = (object) ['description' => $description];
        $params['useOption61ClientId'] = $option61;
        return (object)$params;
    }
}