<?php

namespace App\Models\Netbox\VIRTUALIZATION;

use App\Models\Netbox\BaseModel;

use App\Models\Netbox\DCIM\Interfaces;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Mist\Device;
use App\Models\Gizmo\Dhcp;

#[\AllowDynamicProperties]
class VirtualMachines extends BaseModel
{
    protected $app = "virtualization";
    protected $model = "virtual-machines";

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
        } 
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

    public function generateDhcpId()
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
                return $mistdevice->getDhcpId();
            }
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

    public function createDhcpReservation()
    {

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
        $prefix = Prefixes::getActivePrefixContainingIp($ip);
        if(!(isset($prefix) && $prefix))
        {
            return null;
        }
        $scope = $prefix->getDhcpScope();
        if(!(isset($scope) && $scope))
        {
            return null;
        }
        return $scope->addReservation($dhcpid, $ip, 'NETMAN-' . $this->name);
    }
}