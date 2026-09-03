<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Locations;
use App\Models\Netbox\DCIM\Interfaces;
use App\Models\Netbox\DCIM\FrontPorts;
use App\Models\Netbox\DCIM\RearPorts;
use App\Models\Netbox\DCIM\Racks;
use App\Models\Netbox\DCIM\ModuleBays;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Netbox\IPAM\IpAddresses;
use App\Models\Mist\Device as MistDevice;
use App\Models\Dhcp\SubnetV4;
use App\Models\Dhcp\ReservationV4;
use App\Models\Device\Device;

#[\AllowDynamicProperties]
class Devices extends BaseModel
{
    protected $app = "dcim";
    protected $model = "devices";

    protected $virtualChassis = null;
    protected $cachedIp = null;

    public function location()
    {
        return Locations::find($this->location->id);
    }

    public function address()
    {
        return $this->location()->address();
    }

    public function coordinates()
    {
        return $this->location()->coordinates();
    }

    public function rack()
    {
        if(isset($this->rack->id))
        {
            return Racks::find($this->rack->id);
        }
    }

    public function polling()
    {
        if($this->custom_fields->POLLING === true)
        {
            return $this->location()->polling();
        }
        return false;
    }

    public function alerting()
    {
        if($this->custom_fields->ALERT === true)
        {
            return $this->location()->alerting();
        }
        return false;
    }

    public function interfaces()
    {
        return Interfaces::where('device_id', $this->id)->limit(99999999)->get();
    }

    public function frontPorts()
    {
        return FrontPorts::where('device_id', $this->id)->limit(99999999)->get();
    }

    public function rearPorts()
    {
        return RearPorts::where('device_id', $this->id)->limit(99999999)->get();
    }

    public function moduleBays()
    {
        return ModuleBays::where('device_id', $this->id)->limit(99999999)->get();
    }

    public function addModuleBay($name, $label, $position)
    {
        $params = [
            "device" => $this->id,
            "name"  => $name,
            "label" => $label,
            "position"  => $position,
        ];
        try{
            $new = ModuleBays::create($params);
        } catch (\Exception $e) {
            return "Failed to create Module Bay!";
        }
        return $new;
    }

    public function generateNameLabel()
    {
        if(isset($this->name))
        {
            //Add code to handle STACK member ID
            return $this->name;
        }
    }

    public function generateCableLabels()
    {
        //Add code here to generate CABLE LABELS for this device.
    }

    public function getIpAddress()
    {
        if ($this->cachedIp !== null) {
            return $this->cachedIp;
        }
        $reg = "/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/";
        if(isset($this->primary_ip->address))
        {
            $ip = $this->primary_ip->address;
            if(preg_match($reg, $ip, $hits))
            {
                return $this->cachedIp = $hits[1];
            }
        } elseif(isset($this->custom_fields->ip)) {
            return $this->cachedIp = $this->custom_fields->ip;
        } elseif(isset($this->virtual_chassis->master->id)){
            $master = $this->getVirtualChassisMaster();
            if(isset($master->primary_ip->address))
            {
                $ip = $master->primary_ip->address;
                if(preg_match($reg, $ip, $hits))
                {
                    return $this->cachedIp = $hits[1];
                }
            } elseif(isset($master->custom_fields->ip)) {
                return $this->cachedIp = $master->custom_fields->ip;
            }
        }
    }

    public function getMgmtIp()
    {
        if(isset($this->primary_ip->id))
        {
            $address = IpAddresses::find($this->primary_ip->id);
            if(isset($address->id))
            {
                if(isset($address->address))
                {
                    return $address;
                }
            }
        }
    }

    public function getMgmtInterface()
    {
        if(isset($this->primary_ip->id))
        {
            $address = IpAddresses::find($this->primary_ip->id);
            if(isset($address->id))
            {
                if(isset($address->assigned_object_type) && $address->assigned_object_type == "dcim.interface")
                {
                    if(isset($address->assigned_object_id))
                    {
                        $interface = Interfaces::find($address->assigned_object_id);
                        if(isset($interface->id))
                        {
                            return $interface;
                        }
                    }

                }
            }
        }
    }

    public function getMistDeviceBySerial()
    {
        if(isset($this->serial) && $this->serial)
        {
            return MistDevice::findByserial($this->serial);
        }
    }

    public function getMistDeviceByName()
    {
        if(isset($this->name) && $this->name)
        {
            return MistDevice::findByName($this->name);
        }
    }

    public function getNetmanDevice()
    {
        return Device::where('netbox_type', static::class)->where('netbox_id', $this->id)->first();
    }

    public function renameInterfaces($vcposition)
    {
        $reg = "/^(\S+)-(\d)\/(\d)\/(\d{1,2})$/";
        foreach($this->interfaces() as $interface)
        {
            if(preg_match($reg, $interface->name, $hits))
            {
                if($hits[2] != $vcposition)
                {
                    $name = $hits[1] . "-" . $vcposition . "/" . $hits[3] . "/" . $hits[4];
                    $interface->update(['name'  =>  $name]);
                }
            }
        }
    }

    public function renameInterfaces2($vcposition)
    {
        $reg = "/^(\S+)-(\d)\/(\d)\/(\d{1,2})$/";
        foreach($this->interfaces() as $interface)
        {
            if(preg_match($reg, $interface->name, $hits))
            {
                if($hits[2] != $vcposition)
                {
                    $name = $hits[1] . "-" . $vcposition . "/" . $hits[3] . "/" . $hits[4];
                    $label = $hits[4];
                    //$interface->update(['name'  =>  $name]);
                    $tomodify[] = [
                        'id'    =>  $interface->id,
                        'name'  =>  $name,
                        'label' =>  $label,
                    ];
                }
            }
        }
        $path = env('NETBOX_BASE_URL') . "/api/dcim/interfaces/";
        $response = $this->update2($tomodify, $path);
    }

    public function getVirtualChassis()
    {
        if (!$this->virtualChassis && isset($this->virtual_chassis->id)) {
            $this->virtualChassis = VirtualChassis::find($this->virtual_chassis->id);
        }
        return $this->virtualChassis;
    }

    public function getVirtualChassisMaster()
    {
        $vc = $this->getVirtualChassis();
        if ($vc) {
            return $vc->getMaster();
        }
        return null;
    }

    public function generateDnsName()
    {
        if(isset($this->virtual_chassis->name))
        {
            $name = $this->virtual_chassis->name;
        } else {
            $name = $this->name;
        }
        $newname = str_replace("/","-",$name);
        $newname = str_replace(".","-",$newname);
        $newname = strtolower($newname);
        return $newname;
    }

    public function generateDnsNames()
    {
        $dnsrecords = [];
        if(isset($this->virtual_chassis->id))
        {
            return $dnsrecords;
        }
        $ip = $this->getIpAddress();
        if(!$ip)
        {
            return $dnsrecords;
        }
        $newname = str_replace("/","-",$this->name);
        $newname = str_replace(".","-",$newname);
        $newname = strtolower($newname);
        $dnsrecords[] = [
            'hostname'  =>  $newname,
            'data'      =>  $ip,
            'type'      =>  'a',
        ];
        return $dnsrecords;
    }

    public function generateDhcpId($irb = 0)
    {
        //If dhcp_id is defined on netbox device, return it
        if(isset($this->custom_fields->dhcp_id))
        {
            return strtolower(preg_replace('/[^a-fA-F0-9]/', '', $this->custom_fields->dhcp_id));
        }
        $nmdevice = $this->getNetmanDevice();
        if(isset($nmdevice->id))
        {
            $mac = $nmdevice->getMac();
            if($mac)
            {
                return $mac;
            }
        }
    }

    public function getDhcpReservationByIp()
    {
        $ip = $this->getIpAddress();
        if(isset($ip) && $ip)
        {
            return ReservationV4::findByIp($ip);
        }
    }

    public function getDhcpReservationByDhcpId()
    {
        $dhcpid = $this->generateDhcpId();
        if(isset($dhcpid) && $dhcpid)
        {
            return ReservationV4::findByMac($dhcpid);
        }
    }

    public function generateDhcpReservation()
    {
        $vc = $this->getVirtualChassis();
        if($vc)
        {
            return $vc->generateDhcpReservation();
        }
        $dhcpid = $this->generateDhcpId();
        if(!(isset($dhcpid) && $dhcpid))
        {
            return null;
        }
        $ip = $this->getIpAddress();
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

    public function createDhcpReservation()
    {
        $params = $this->generateDhcpReservation();
        if(!(isset($params) && $params))
        {
            return null;
        }
        $scope = SubnetV4::findByIp($params['ipaddress']);
        if(!(isset($scope) && $scope))
        {
            return null;
        }
        return ReservationV4::create($params['ipaddress'], $params['hwaddress'], $params['description']);
    }
    
}