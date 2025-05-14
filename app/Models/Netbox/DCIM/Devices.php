<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Locations;
use App\Models\Netbox\DCIM\Interfaces;
use App\Models\Netbox\DCIM\FrontPorts;
use App\Models\Netbox\DCIM\RearPorts;
use App\Models\Netbox\DCIM\Racks;
use App\Models\Netbox\DCIM\ModuleBays;

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
			'agg'	=>	2,
			'wlc'	=>	17,
			'wbr'	=>	8,
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
        /*
        $reg = "/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/";
        if(isset($this->primary_ip->address))
        {
            $ip = $this->primary_ip->address;
            preg_match($reg, $ip, $hits);
            return $hits[1];
        }
        /**/

        if(isset($this->custom_fields->ip)) {
            return $this->custom_fields->ip;
        } elseif(isset($this->virtual_chassis->master->id)) {
            $master = self::find($this->virtual_chassis->master->id);
            if(isset($master->custom_fields->ip))
            {
                return $master->custom_fields->ip;
            }
        }     
    }
}