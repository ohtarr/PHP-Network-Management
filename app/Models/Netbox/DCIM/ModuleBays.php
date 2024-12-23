<?php

namespace App\Models\Netbox\DCIM;

use App\Models\Netbox\BaseModel;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Modules;

#[\AllowDynamicProperties]
class ModuleBays extends BaseModel
{
    protected $app = "dcim";
    protected $model = "module-bays";

    public function device()
    {
        return Devices::where('id',$this->device->id);
    }

    public function installNewModule($typeid)
    {
        $new = [
            "device"    =>  $this->device->id,
            "module_bay" => $this->id ,
            "module_type" => $typeid,
        ];
        try{
            $module = Modules::create($new);
        } catch (\Exception $e) {
            return "Failed to create Module Bay!";
        }
        return $module;
    }

}