<?php

namespace App\Models\Device\Juniper;

class Juniper extends \App\Models\Device\Device
{
    protected static $singleTableSubclasses = [
    ];

    protected static $singleTableType = __CLASS__;

/*     protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    } */

    protected $casts = [
        'data'  =>  'array',
    ];

    //List of commands to run during a scan of this device.
    public $scan_cmds = [
        'version'		=>	'show version | display json',
        'inventory'		=>	'show chassis hardware | display json',
        'run'			=>	'show configuration | display inheritance no-comments | display json',
        'interface'		=>	'show interfaces | display json',
        'lldp'			=>	'show lldp neighbors | display json',
        'run_set'		=>	'show configuration | display set',
    ];

    //Use SSH2 for connection
    public function exec_cmds($cmds, $timeout = null)
    {
        return $this->exec_cmds_2($cmds, $timeout);
    }

    /*
    Find the name of this device from DATA.
    Returns string (device name).
    */
    public function getName()
    {
        $run = $this->getLatestOutputs('run')->data;
        if(isset($run['configuration']['system']['host-name']))
        {
            return $run['configuration']['system']['host-name'];
        }
    }

    /*
    Find the serial of this device from DATA.
    Returns string (device serial).
    */
    public function getSerial()
    {
        $inv = $this->getLatestOutputs('inventory')->data;
        if(isset($inv["chassis-inventory"][0]["chassis"][0]["serial-number"][0]['data']))
        {
            return $inv["chassis-inventory"][0]["chassis"][0]["serial-number"][0]['data'];
        }
    }

    /*
    Find the model of this device from DATA.
    Returns string (device model).
    */
    public function getModel()
    {
        $inv = $this->getLatestOutputs('inventory')->data;
        if(isset($inv["chassis-inventory"][0]["chassis"][0]["description"][0]['data']))
        {
            return $inv["chassis-inventory"][0]["chassis"][0]["description"][0]['data'];
        }
    }

    public function getChassisHardware()
    {
        $array = $this->getLatestOutputs('inventory')->data;
        $chassis['name'] = $array["chassis-inventory"][0]["chassis"][0]['name'][0]['data'];
        $chassis['serial'] = $array["chassis-inventory"][0]["chassis"][0]['serial-number'][0]['data'];			
        $chassis['description'] = $array["chassis-inventory"][0]["chassis"][0]['description'][0]['data'];
        foreach($array["chassis-inventory"][0]["chassis"][0]['chassis-module'] as $module)
        {
            $keys = [
                'name',
                'part-number',
                'serial-number',
                'description',
                'clei-code',
                'model-number',
            ];
            unset($tmpmodule);
            foreach($keys as $key)
            {
                $tmpmodule[$key] = $module[$key][0]['data'];
            }
            
            foreach($module['chassis-sub-module'] as $submodule)
            {
                $keys = [
                    'name',
                    'part-number',
                    'serial-number',
                    'description',
                ];
                unset($tmpsm);
                foreach($keys as $key)
                {
                    $tmpsm[$key] = $submodule[$key][0]['data'];
                }
                foreach($submodule['chassis-sub-sub-module'] as $subsubmodule)
                {
                    $keys = [
                        'name',
                        'version',
                        'part-number',
                        'serial-number',
                        'description',
                    ];
                    unset($tmpssm);
                    foreach($keys as $key)
                    {
                        $tmpssm[$key] = $subsubmodule[$key][0]['data'];
                    }
                    $tmpsm['subsubmodules'][] = $tmpssm;
                }
                $tmpmodule['submodules'][] = $tmpsm;
            }
            $chassis['modules'][] = $tmpmodule;
        }
        return $chassis;
    }

	public function getVirtualChassisHardware()
	{
		$fpcreg = "/FPC (\d+)/";
		$chassis = null;
        $array = $this->getLatestOutputs('inventory')->data;
		if(!is_array($array))
		{
			return null;
		}
		foreach($array["chassis-inventory"][0]["chassis"][0]['chassis-module'] as $module)
		{
			unset($tmpmodule);
			if(preg_match($fpcreg, $module['name'][0]['data'], $hits))
			{
				$tmpmodule['name'] = $module['name'][0]['data'];
				$tmpmodule['model-number'] = $module['model-number'][0]['data'];
				$tmpmodule['serial'] = $module['serial-number'][0]['data'];
				$tmpmodule['description'] = $module['description'][0]['data'];
				$chassis[$hits[1]] = $tmpmodule;
			}
		}
		return $chassis;
	}

}
