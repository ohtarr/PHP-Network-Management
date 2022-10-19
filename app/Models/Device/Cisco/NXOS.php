<?php

namespace App\Models\Device\Cisco;

class NXOS extends \App\Models\Device\Cisco\Cisco
{
    protected static $singleTableSubclasses = [
    ];
    
    protected static $singleTableType = __CLASS__;

    /*
     Find the serial of this device from DATA.
     Returns string (device serial).
     */
    public function getSerial()
    {
        //Reg to grab the serial from the show inventory.
        $reg = "/SN:\s+(\S+)/";
        if (preg_match($reg, $this->data['inventory'], $hits)) {
            return $hits[1];
        }
    }

    /*
    Find the model of this device from DATA.
    Returns string (device model).
    */
    public function getModel()
    {
        //Reg to grab the model from the show inventory.
        $reg = "/PID:\s+(\S+)/";
        if (preg_match($reg, $this->data['inventory'], $hits)) {
            return $hits[1];
        }
    }
}
