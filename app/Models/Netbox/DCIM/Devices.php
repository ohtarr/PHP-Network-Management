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
use App\Models\Mist\Device;
use App\Models\Gizmo\Dhcp;

#[\AllowDynamicProperties]
class Devices extends BaseModel
{
    protected $app = "dcim";
    protected $model = "devices";

    public static function getRoleMapping()
    {
        return [
			'rwa'	=>	6,
			'swa'	=>	1,
			'swd'	=>	3,
			'per'	=>	5,
			'pcr'	=>	5,
			'rrr'	=>	6,
			'rfw'	=>	7,
            'fwc'   =>  7,
			'agg'	=>	2,
			'wlc'	=>	17,
			'wbr'	=>	8,
            'wap'   =>  20,
            'owa'   =>  20,
            'oob'   =>  11,
		];
    }

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
        $reg = "/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/";
        if(isset($this->primary_ip->address))
        {
            $ip = $this->primary_ip->address;
            if(preg_match($reg, $ip, $hits))
            {
                return $hits[1];
            }
        } elseif(isset($this->custom_fields->ip)) {
            return $this->custom_fields->ip;
        } elseif(isset($this->virtual_chassis->master->id)){
            $master = self::find($this->virtual_chassis->master->id);
            if(isset($master->primary_ip->address))
            {
                $ip = $master->primary_ip->address;
                if(preg_match($reg, $ip, $hits))
                {
                    return $hits[1];
                }
            } elseif(isset($master->custom_fields->ip)) {
                return $master->custom_fields->ip;            
            }
        }
    }

    public function getMistDeviceBySerial()
    {
        if(isset($this->serial) && $this->serial)
        {
            return Device::findByserial($this->serial);
        }
    }

    public function getMistDeviceByName()
    {
        if(isset($this->name) && $this->name)
        {
            return Device::findByName($this->name);
        }
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
        if(isset($this->virtual_chassis->id))
        {
            return VirtualChassis::find($this->virtual_chassis->id);
        }
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
            return $this->custom_fields->dhcp_id;
        }

        if(isset($this->device_type->manufacturer->name) && $this->device_type->manufacturer->name == "Juniper")
        {
            $mistdevice = $this->getMistDeviceBySerial();
            if(isset($mistdevice->mac) && $mistdevice->mac)
            {
                return $mistdevice->generateDhcpId($irb);
            }
        }
        if(!$this->name)
        {
            return null;
        }
        //convert device name to hex and return it.
        $hex = bin2hex($this->name);
        $formattedHex = chunk_split($hex, 2, '-');
        return rtrim($formattedHex, '-');
    }

    public function getDhcpReservationByIp()
    {
        $ip = $this->getIpAddress();
        if(isset($ip) && $ip)
        {
            return Dhcp::getReservationByIp($ip);
        }
    }

    public function getDhcpReservationByDhcpId()
    {
        $dhcpid = $this->generateDhcpId();
        if(isset($dhcpid) && $dhcpid)
        {
            return Dhcp::getReservationsByMac($dhcpid);
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
        //return $scope->addReservation($dhcpid, $ip, 'NETMAN-' . $this->name);
    }

    public function createDhcpReservation()
    {
        $params = $this->generateDhcpReservation();
        $scope = Dhcp::find($params['scopeId']);
        if(!(isset($scope) && $scope))
        {
            return null;
        }
        return $scope->addReservation($params['clientId'], $params['ipAddress'], $params['description']);
    }
}