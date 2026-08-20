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

    //List of outputs to collect during a scan of this device.
    public $scan_outputs = [
        'run'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'show configuration | display set',
        ],
        'version'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'show version | display json',
        ],
        'inventory'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'show chassis hardware | display json',
        ],
        'interface'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'show interfaces | display json',
            'timeout'	=>	60,
        ],
        'lldp'          =>  [
            'method'    =>  'ssh',
            'input'     =>  'show lldp neighbors detail | display json',
        ],
        'run_json'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'show configuration | display json',
        ],
        'sessions'      =>  [
            'method'    =>  'ssh',
            'input'     =>  'show security flow statistics | display json',
        ],
    ];

    /*
    Find the name of this device from DATA.
    Returns string (device name).
    */
    public function getName()
    {
        $output = $this->getLatestOutputs('run_json');
        if (!$output) {
            return null;
        }
        $run = $output->dataArray;
        if (!is_array($run)) {
            return null;
        }
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
        $output = $this->getLatestOutputs('inventory');
        if (!$output) {
            return null;
        }
        $inv = $output->dataArray;
        if (!is_array($inv)) {
            return null;
        }
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
        $output = $this->getLatestOutputs('inventory');
        if (!$output) {
            return null;
        }
        $inv = $output->dataArray;
        if (!is_array($inv)) {
            return null;
        }
        if(isset($inv["chassis-inventory"][0]["chassis"][0]["description"][0]['data']))
        {
            return $inv["chassis-inventory"][0]["chassis"][0]["description"][0]['data'];
        }
    }

    public function getChassisHardware()
    {
        $output = $this->getLatestOutputs('inventory');
        if (!$output) {
            return null;
        }
        $array = $output->dataArray;
        if (!is_array($array)) {
            return null;
        }
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
        $output = $this->getLatestOutputs('inventory');
		if (!$output) {
			return null;
		}
        $array = $output->dataArray;
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

    /*
    Find the management MAC address of this device.
    Tries each known source in order and returns the first hit:
      1. Mist (source of truth when the device is Mist-managed)
      2. The 'irb' interface's hardware address from 'show interfaces | display json'
    Additional sources can be appended to this list later.
    Returns string (bare lowercase hex, no separators) or null.
    */
    public function getMac()
    {
        $mac = $this->getMacFromMist();
        if ($mac) {
            return $mac;
        }

        return $this->getMacFromInterfaces();
    }

    protected function getMacFromMist()
    {
        $nbdevice = $this->getNetboxDevice();
        if (!$nbdevice) {
            return null;
        }

        $mistdevice = $nbdevice->getMistDeviceBySerial();
        if (!$mistdevice) {
            $mistdevice = $nbdevice->getMistDeviceByName();
        }

        if (!isset($mistdevice->mac) || !$mistdevice->mac) {
            return null;
        }

        return strtolower(preg_replace('/[^a-fA-F0-9]/', '', $mistdevice->mac));
    }

    /*
    NOTE: 'current-physical-address' is the standard Junos field name for a
    physical interface's MAC in 'show interfaces | display json', but this has
    not been verified against real captured output in this repo (same caveat
    as getNeighbors() below for the LLDP JSON shape). 'hardware-physical-address'
    is checked as a fallback in case the field is named differently on some
    Junos versions.
    */
    protected function getMacFromInterfaces()
    {
        $output = $this->getLatestOutputs('interface');
        if (!$output) {
            return null;
        }

        $array = $output->dataArray;
        if (!is_array($array)) {
            return null;
        }

        $physicalInterfaces = $array['interface-information'][0]['physical-interface'] ?? null;
        if (!is_array($physicalInterfaces)) {
            return null;
        }

        foreach ($physicalInterfaces as $interface) {
            $name = $interface['name'][0]['data'] ?? null;
            if ($name !== 'irb') {
                continue;
            }

            $mac = $interface['current-physical-address'][0]['data']
                ?? $interface['hardware-physical-address'][0]['data']
                ?? null;

            if ($mac) {
                return strtolower(preg_replace('/[^a-fA-F0-9]/', '', $mac));
            }
        }

        return null;
    }

    public function getNeighbors2(): array
    {
        $results = [];
        $neighborsarray = $this->getLatestOutputs('lldp')->dataArray['lldp-neighbors-information'][0]['lldp-neighbor-information'];
        foreach($neighborsarray as $neighborarray)
        {
            $tmp = [];
            $tmp['local_port'] = $neighborarray['lldp-local-interface'][0]["data"] ?? null;
            $tmp['name'] = $neighborarray['lldp-remote-system-name'][0]["data"] ?? null;
            $remotechassistype = $neighborarray['lldp-remote-chassis-id-subtype'][0]["data"] ?? null;
            if($remotechassistype == "Mac address"){
                $tmp['mac'] = $neighborarray['lldp-remote-chassis-id'][0]["data"] ?? null;
            }
            $remoteinterfacetype = $neighborarray['lldp-remote-port-id-subtype'][0]["data"] ?? null;
            if($remoteinterfacetype == "Interface name"){
                $tmp['remote_port'] = $neighborarray['lldp-remote-port-id'][0]["data"] ?? null;
            } else {
                $tmp['remote_port'] = $neighborarray['lldp-remote-port-description'][0]["data"] ?? null;
            }
            $results[] = $tmp;
        }
        return $results;
    }

    /*
    Find neighbor devices discovered via LLDP from DATA.
    Parses 'show lldp neighbors | display json' output.
    NOTE: the 'lldp-neighbors-information' / 'lldp-neighbor-information'
    JSON shape used below follows the standard Junos schema for this
    command, but has NOT been verified against a real sample captured
    in this repo -- field names/nesting may need correction against
    actual device output. media_type is intentionally omitted: there is
    no sampled Junos interface JSON to confirm which field would map to it.
    lldp-local-interface, lldp-local-port-id, lldp-remote-port-id, and
    lldp-remote-port-description are confirmed field names from real device
    output -- Junos is inconsistent about which of the local pair actually
    holds the interface name, so local_port prefers whichever candidate
    looks like one (two letters then a hyphen, e.g. "ge-", "xe-", "et-").
    remote_port instead trusts lldp-remote-port-id-subtype: when it reports
    "Interface name", lldp-remote-port-id is used; otherwise
    lldp-remote-port-description is used.
    Returns an array of neighbor arrays (see Device::getNeighbors() for keys).
    */
    public function getNeighbors(): array
    {
        $result = [];

        $lldp = $this->getLatestOutputs('lldp');
        if (!$lldp) {
            return $result;
        }

        $array = $lldp->dataArray;
        if (!is_array($array)) {
            return $result;
        }

        if (!isset($array['lldp-neighbors-information'][0]['lldp-neighbor-information'])
            || !is_array($array['lldp-neighbors-information'][0]['lldp-neighbor-information']))
        {
            return $result;
        }

        foreach ($array['lldp-neighbors-information'][0]['lldp-neighbor-information'] as $neighbor)
        {
            if (!is_array($neighbor)) {
                continue;
            }

            $entry = [];

            // Remote system name -- strip domain suffix if FQDN is reported.
            if (isset($neighbor['lldp-remote-system-name'][0]['data']) && $neighbor['lldp-remote-system-name'][0]['data'] !== '')
            {
                $entry['name'] = explode('.', $neighbor['lldp-remote-system-name'][0]['data'])[0];
            }

            // Remote chassis id is only a MAC address when the subtype says so
            // (subtype can also be "Interface alias", "Locally assigned", etc).
            $subtype   = $neighbor['lldp-remote-chassis-id-subtype'][0]['data'] ?? null;
            $chassisId = $neighbor['lldp-remote-chassis-id'][0]['data'] ?? null;
            if ($subtype !== null && $chassisId !== null && stripos($subtype, 'mac') !== false)
            {
                $entry['mac'] = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $chassisId));
            }

            // Junos LLDP output is inconsistent about which field holds the real
            // interface name. Prefer whichever of lldp-local-port-id /
            // lldp-local-interface looks like one (two letters then a hyphen,
            // e.g. "ge-", "xe-", "et-"); otherwise fall back to lldp-local-port-id.
            $localPortId = $neighbor['lldp-local-port-id'][0]['data']   ?? null;
            $localIface  = $neighbor['lldp-local-interface'][0]['data'] ?? null;
            $ifPattern   = '/^[a-z]{2}-/i';

            if ($localPortId && preg_match($ifPattern, $localPortId)) {
                $entry['local_port'] = $localPortId;
            }
            elseif ($localIface && preg_match($ifPattern, $localIface)) {
                $entry['local_port'] = $localIface;
            }
            elseif ($localPortId) {
                $entry['local_port'] = $localPortId;
            }
            elseif ($localIface) {
                $entry['local_port'] = $localIface;
            }

            // Remote port name is authoritative when the subtype says the port id IS
            // an interface name; otherwise fall back to the port description.
            $subtypeRemote = $neighbor['lldp-remote-port-id-subtype'][0]['data'] ?? null;
            $remotePortId  = $neighbor['lldp-remote-port-id'][0]['data']          ?? null;
            $remoteDesc    = $neighbor['lldp-remote-port-description'][0]['data'] ?? null;

            if ($subtypeRemote === 'Interface name' && $remotePortId) {
                $entry['remote_port'] = $remotePortId;
            }
            elseif ($remoteDesc) {
                $entry['remote_port'] = $remoteDesc;
            }

            $normalized = array_filter(
                [
                    'name'        => $entry['name']        ?? null,
                    'mac'         => $entry['mac']          ?? null,
                    'local_port'  => $entry['local_port']   ?? null,
                    'remote_port' => $entry['remote_port']  ?? null,
                ],
                fn($v) => $v !== null && $v !== ''
            );

            if (!empty($normalized)) {
                $result[] = $normalized;
            }
        }

        return $result;
    }

}
