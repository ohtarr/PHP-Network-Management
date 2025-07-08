<?php

namespace App\Models\Netbox\IPAM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\IPAM\Prefixes;
use App\Models\Gizmo\Dhcp;
use IPv4\SubnetCalculator;

#[\AllowDynamicProperties]
class IpRanges extends BaseModel
{
    protected $app = "ipam";
    protected $model = "ip-ranges";

    public function parent()
    {
        $query = Prefixes::where('contains',$this->start_address)->where('ordering','_depth');
        if(isset($this->vrf->id))
        {
            $query = $query->where('vrf_id',$this->vrf->id);
        }
        return $query->get()->last();
    }

    public function children()
    {
        $return = [];
        $parent = $this->parent();
        if(!$parent)
        {
            return null;
        }
        $addresses = $parent->getIpAddresses();
        $params = $this->getParams();
        $start = ip2long($params['start_address']);
        $end = ip2long($params['end_address']);
        foreach($addresses as $address)
        {
            $long = ip2long($address->cidr()['ip']);
            if($long >= $start && $long <= $end)
            {
                $return[] = $address;
            }
        }
        return collect($return);
    }

    public function getSite()
    {
        $parent = $this->parent();
        if($parent)
        {
            return $parent->getSite();
        }
    }

    public function getParams()
    {
        $reg = "/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/";
        preg_match($reg, $this->start_address, $hits);
        $ipcalc = new SubnetCalculator($hits[1], $hits[2]);
        $return['name'] = $this->description;
        $return['description'] = $this->description;
        $return['start_address'] = $hits[1];
        preg_match($reg, $this->end_address, $hits);
        $return['end_address'] = $hits[1];
        $return['network'] = $ipcalc->getNetworkPortion();
        $return['bitmask'] = $hits[2];
        $return['netmask'] = $ipcalc->getSubnetMask();
        foreach($this->custom_fields as $key => $value)
        {
            $return[$key] = $this->custom_fields->$key;
        }
        return $return;
    }

    public function getDhcpScope()
    {
        return Dhcp::find($this->getParams()['network']);
    }

    public function generateDhcpScopeParams()
    {
        $rangeparams = $this->getParams();
        $params = [
            "name"			    => $rangeparams['name'],
            "description"	    => $rangeparams['description'],
            "subnetMask"		=> $rangeparams['netmask'],
            "startRange"		=> $rangeparams['start_address'],
            "endRange"		    => $rangeparams['end_address'],
        ];
        if(isset($this->custom_fields->gateway))
        {
            $optionsparams[] = [
                'optionId'  =>  "3",
                'value'     =>  $this->custom_fields->gateway,
            ];
        }
        if(isset($this->custom_fields->dns1))
        {
            $dns[] = $this->custom_fields->dns1;
        }
        if(isset($this->custom_fields->dns2))
        {
            $dns[] = $this->custom_fields->dns2;
        }
        if(isset($this->custom_fields->dns3))
        {
            $dns[] = $this->custom_fields->dns3;
        }
        if(!empty($dns))
        {
            $optionsparams[] = [
                'optionId'  =>  "6",
                'value'     =>  $dns,                
            ];
        }
        $optionsparams[] = [
                'optionId'  =>  "15",
                'value'     =>  ["kiewitplaza.com"],
        ];
        if(isset($this->custom_fields->cm1))
        {
            $cm[] = $this->custom_fields->cm1;
        }
        if(isset($this->custom_fields->cm2))
        {
            $cm[] = $this->custom_fields->cm2;
        }
        if(!empty($cm))
        {
            $optionsparams[] = [
                'optionId'  =>  "150",
                'value'     =>  $cm,                
            ];
        }

        $params['dhcpOptions'] = $optionsparams;
        return $params;
    }

}