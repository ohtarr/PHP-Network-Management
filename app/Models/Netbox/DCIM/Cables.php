<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Locations;
use App\Models\Netbox\DCIM\Interfaces;
use App\Models\Netbox\DCIM\FrontPorts;
use App\Models\Netbox\DCIM\RearPorts;

#[\AllowDynamicProperties]
class Cables extends BaseModel
{
    protected $app = "dcim";
    protected $model = "cables";

    protected $map = [
        'dcim.interface'    =>  Interfaces::class,
        'dcim.frontport'    =>  FrontPorts::class,
        'dcim.rearport'     =>  RearPorts::class,
    ];

    public function getAEnd()
    {
        $return = null;
        if(!isset($this->a_terminations))
        {
            return null;
        }
        foreach($this->a_terminations as $termination)
        {
            foreach($this->map as $key => $model)
            {
                if($key == $termination->object_type)
                {
                    $return[] = $model::find($termination->object_id);
                }
            }
        }
        return collect($return);
    }

    public function getBEnd()
    {
        $return = null;
        if(!isset($this->b_terminations))
        {
            return null;
        }
        foreach($this->b_terminations as $termination)
        {
            foreach($this->map as $key => $model)
            {
                if($key == $termination->object_type)
                {
                    $return[] = $model::find($termination->object_id);
                }
            }
        }
        return collect($return);
    }

    public function generateReport()
    {
        $a = $this->getAEnd()->first();
        if(!$a)
        {
            return null;
        }
        $adevice = $a->device();
        if(!$adevice)
        {
            return null;
        }
        $arack = $a->device()->rack();
        if(!$arack)
        {
            return null;
        }
        $aru = intval($adevice->position);
        if(!$aru)
        {
            return null;
        }

        $b = $this->getBEnd()->first();
        if(!$b)
        {
            return null;
        }
        $bdevice = $b->device();
        if(!$bdevice)
        {
            return null;
        }
        $brack = $b->device()->rack();
        if(!$brack)
        {
            return null;
        }
        $bru = intval($bdevice->position);
        if(!$bru)
        {
            return null;
        }

        $alabel = $arack->name . "_RU" . $aru . "_" . $a->label;
        $blabel = $brack->name . "_RU" . $bru . "_" . $b->label;
        //$label = [$alabel,$blabel];

        $aflabel = $adevice->name . "_" . $a->label;
        $bflabel = $bdevice->name . "_" . $b->label;
        //$flabel = [$aflabel,$bflabel];

        $length = "";
        $length_unit_label = "";

        if(isset($this->length) && isset($this->length_unit->label))
        {
            $length = $this->length;
            $length_unit_label = $this->length_unit->label;            
        }

        $return = [
            'type'                  =>  $this->type,
            'color'                 =>  $this->color,
            'length'                =>  $length,
            'length_unit'           =>  $length_unit_label,
            'a_site'                =>  $adevice->site->name,
            'a_location'            =>  $adevice->location->name,
            'a_rack'                =>  $adevice->rack->name,
            'a_ru'                  =>  $adevice->position,
            'a_device_name'         =>  $adevice->name,
            'a_device_port'         =>  $a->label,
            'a_device_port_type'    =>  $a->type->label,
            'b_site'                =>  $bdevice->site->name,
            'b_location'            =>  $bdevice->location->name,
            'b_rack'                =>  $bdevice->rack->name,
            'b_ru'                  =>  $bdevice->position,
            'b_device_name'         =>  $bdevice->name,
            'b_device_port'         =>  $b->label,
            'b_device_port_type'    =>  $b->type->label,
            'label1'                =>  $alabel,
            'label2'                =>  $blabel,
            'friendly_label1'       =>  $aflabel,
            'friendly_label2'       =>  $bflabel,            
        ];
        return $return;
    }

    public function generateLabel()
    {
        $report = $this->generateReport();
        return [
            'label1'    =>  $report['label1'],
            'label2'    =>  $report['label2'],
        ];
    }

}