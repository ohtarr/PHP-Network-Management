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

    public function getDhcpReservationByIp()
    {
        $ip = $this->getIpAddress();
        if(isset($ip) && $ip)
        {
            return Dhcp::getReservationByIp($ip);
        }
    }
}