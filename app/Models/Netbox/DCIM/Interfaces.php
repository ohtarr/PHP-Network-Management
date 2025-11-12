<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Devices;

#[\AllowDynamicProperties]
class Interfaces extends BaseModel
{
    protected $app = "dcim";
    protected $model = "interfaces";

    public function device()
    {
        return Devices::find($this->device->id);
    }

    public function parent()
    {
        if(isset($this->parent->id))
        {
            return static::find($this->parent->id);
        }
    }

    public function polling()
    {
        if($this->custom_fields->POLLING === true)
        {
            if($parent = $this->parent())
            {
                if($parent->polling() === true)
                {
                    return true;
                }
            } elseif($device = $this->device()) {
                if($device->polling() === true)
                {
                    return true;
                }
            }
        }
        return false;
    }

    public function alerting()
    {
        if($this->custom_fields->ALERT === true)
        {
            if($parent = $this->parent())
            {
                if($parent->alerting() === true)
                {
                    return true;
                }
            } elseif($device = $this->device()) {
                if($device->alerting() === true)
                {
                    return true;
                }
            }
        }
        return false;
    }

    public function abbreviateName()
    {
        $convert = [
            'ethernet'                  =>  'eth',
            'fastethernet'              =>  'fe',
            'gigabitethernet'           =>  'gi',
            'tengigabitethernet'        =>  'te',
            'fortygigabitethernet'      =>  'fo',
            'hundredgigabitethernet'    =>  'hu',
            'tengige'                   =>  'te',
            'HundredGigE'               =>  'hu',
            'TenGigE'                   =>  'te',
            'Bundle-Ether'              =>  'be',
        ];
        foreach($convert as $long => $short)
        {
            $reg = "/^" . $long . "(\S+)/";
            if(preg_match($reg, strtolower($this->name), $hits))
            {
                $new = $short . $hits[1];
                break;
            }
        }
        if(isset($new))
        {
            return $new;
        } else {
            return strtolower($this->name);
        }
    }

    public function generateDnsName()
    {
        $intname = $this->abbreviateName();
        $intname = str_replace("/","-",$intname);
        $intname = str_replace(".","-",$intname);
        $devicename = strtolower($this->device()->name);
        $devicename = str_replace("/","-",$devicename);
        $devicename = str_replace(".","-",$devicename);
        $fullname = $intname . "." . $devicename;
        return $fullname;
    }

}