<?php

namespace App\Models\Device\Cisco;

use App\Models\Device\Device;
use App\Models\Device\Cisco\CiscoCollection as Collection;
use App\Models\Device\Cisco\IOS\CiscoIOS;
use App\Models\Device\Cisco\IOSXE\CiscoIOSXE;
use App\Models\Device\Cisco\IOSXR\CiscoIOSXR;
use App\Models\Device\Cisco\NXOS\CiscoNXOS;
use App\Models\Device\Cisco\ASA\CiscoASA;
use App\Models\Device\Cisco\FIREPOWER\CiscoFirepower;

class Cisco extends Device
{
    protected static $singleTableSubclasses = [
        CiscoIOS::class,
        CiscoIOSXE::class,
        CiscoIOSXR::class,
        CiscoNXOS::class,
        CiscoASA::class,
        CiscoFirepower::class,
    ];
    protected static $singleTableType = __CLASS__;

    public $promptreg = '/\S*[#|>]\s*\z/';

    public $precli = [
        'term length 0',
        'terminal pager 0',
    ];

    public $cli_timeout = 20;

    public $discover_commands = [
        'sh version',
        'sh version running',
        'show chassis detail',
    ];

    public $discover_regex = [
        CiscoIOS::class     => [
            '/cisco ios software/i',
        ],
        CiscoIOSXE::class   => [
            '/ios-xe/i',
            '/package:/i',
        ],
        CiscoIOSXR::class   => [
            '/ios xr/i',
            '/iosxr/i',
        ],
        CiscoNXOS::class    => [
            '/Cisco Nexus/i',
            '/nx-os/i',
        ],
        CiscoASA::class     => [
            '/Cisco Adaptive Security Appliance/i',
        ],
        CiscoFirepower::class     => [
            '/Cisco Firepower/i',
        ],
    ];
    //List of outputs to collect during a scan of this device.
    public $scan_outputs = [
        'run'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh run',
        ],
        'version'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh version',
        ],
        'interfaces'    =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh interfaces',
        ],
        'inventory'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh inventory',
        ],
        'dir'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'dir',
        ],
        'cdp'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh cdp neighbor detail',
        ],
        'lldp'          =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh lldp neighbor detail',
        ],
        'mac'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh mac address-table',
        ],
        'arp'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'sh ip arp',
        ],
    ];

    public function newCollection(array $models = []) 
    { 
       return new Collection($models);
    }

    /*
    Find the name of this device from DATA.
    Returns string (device name).
    */
    public function getName()
    {
        if(!isset($this->data['run']))
        {
            return null;
        }
        $reg = "/hostname (\S+)/";
        if (preg_match($reg, $this->data['run'], $hits)) {
            return $hits[1];
        }
    }

    /*
    Find the serial of this device from DATA.
    Returns string (device serial).
    */
    public function getSerial()
    {
        if(!isset($this->data['version']))
        {
            return null;
        }
        $reg = "/^Processor board ID (\S+)/m";
        if (preg_match($reg, $this->data['version'], $hits)) {
            return $hits[1];
        }
    }

    /*
    Find the model of this device from DATA.
    Returns string (device model).
    */
    public function getModel()
    {
        if(!isset($this->data['version']))
        {
            return null;
        }
        if (preg_match('/.*isco\s+(WS-\S+)\s.*/', $this->data['version'], $reg)) {
            return $reg[1];
        }
        if (preg_match('/.*isco\s+(OS-\S+)\s.*/', $this->data['version'], $reg)) {
            return $reg[1];
        }
        if (preg_match('/.*ardware:\s+(\S+),.*/', $this->data['version'], $reg)) {
            return $reg[1];
        }
        if (preg_match('/.*ardware:\s+(\S+).*/', $this->data['version'], $reg)) {
            return $reg[1];
        }
        if (preg_match('/^[c,C]isco\s(\S+)\s\(.*/m', $this->data['version'], $reg)) {
            return $reg[1];
        }
    }

    /*
    Determine the neighbors of this Cisco device by parsing the latest stored CDP and LLDP outputs.
    Also consults the latest 'interfaces' output to resolve the media type (copper/fiber/etc.) for
    each local port.

    CDP entries are collected first (no MAC available from CDP).
    LLDP entries are then merged in, keyed by neighbor NAME — LLDP wins for any field it provides
    because it carries the neighbor MAC address. Port names from both protocols are expanded to their
    full canonical form via expandInterfaceName() before being used as lookup keys, so that
    abbreviated names (e.g. "Gi0/1") and full names (e.g. "GigabitEthernet0/1") resolve correctly.

    Keys with null or empty-string values are omitted from each neighbor array.

    Returns an array of neighbor arrays, each containing any of:
      - name        : neighbor device hostname
      - mac         : neighbor MAC address (lowercase, no separators)
      - local_port  : local interface name (full canonical form)
      - remote_port : remote interface name on the neighbor (full canonical form)
      - media_type  : physical media type derived from 'sh interfaces' (e.g. 'copper', 'fiber')
    */
    public function getNeighbors(): array
    {
        // ----------------------------------------------------------------
        // 1. Build a local_port -> media_type map from 'interfaces' output
        //    Keys are stored in expanded (full) form for consistent lookup.
        // ----------------------------------------------------------------
        $mediaMap = [];
        $interfacesOutput = $this->getLatestOutputs('interfaces');
        if ($interfacesOutput && !empty($interfacesOutput->data)) {
            // Split into per-interface blocks on lines that start with a non-whitespace
            // character followed by " is " (the standard Cisco interface header line).
            $blocks = preg_split('/(?=^\S)/m', $interfacesOutput->data);
            foreach ($blocks as $block) {
                // Extract the interface name from the first line of the block
                if (!preg_match('/^(\S+)\s+is\s+/m', $block, $ifMatch)) {
                    continue;
                }
                $ifName = $this->expandInterfaceName($ifMatch[1]);

                // Extract "Media Type is <value>" and normalize to canonical form
                if (preg_match('/Media Type is\s+(\S+)/i', $block, $mediaMatch)) {
                    $normalized = $this->normalizeMediaType($mediaMatch[1]);
                    if ($normalized !== null) {
                        $mediaMap[$ifName] = $normalized;
                    }
                }
            }
        }

        // ----------------------------------------------------------------
        // 2. Parse CDP output  (sh cdp neighbor detail)
        //    Keyed by neighbor name (lowercased).
        // ----------------------------------------------------------------
        $neighbors = [];

        $cdpOutput = $this->getLatestOutputs('cdp');
        if ($cdpOutput && !empty($cdpOutput->data)) {
            // CDP neighbor blocks are separated by lines of dashes
            $blocks = preg_split('/^-{3,}\s*$/m', $cdpOutput->data);
            foreach ($blocks as $block) {
                $block = trim($block);
                if (empty($block)) {
                    continue;
                }

                $entry = [];

                // Device ID — strip any trailing parenthetical (e.g. serial number)
                if (preg_match('/^Device ID:\s*(\S+)/m', $block, $m)) {
                    $entry['name'] = preg_replace('/\(.*\)$/', '', trim($m[1]));
                }

                // Local interface and remote port on the same line:
                // "Interface: GigabitEthernet0/1,  Port ID (outgoing port): GigabitEthernet0/2"
                if (preg_match('/Interface:\s*(\S+?),\s*Port ID \(outgoing port\):\s*(\S+)/i', $block, $m)) {
                    $entry['local_port']  = $this->expandInterfaceName(trim($m[1]));
                    $entry['remote_port'] = $this->expandInterfaceName(trim($m[2]));
                }

                if (empty($entry['name'])) {
                    continue;
                }

                $neighbors[strtolower($entry['name'])] = $entry;
            }
        }

        // ----------------------------------------------------------------
        // 3. Parse LLDP output  (sh lldp neighbor detail)
        //    Merge into the CDP-keyed array by neighbor name.
        //    LLDP fields override CDP fields when both are present.
        // ----------------------------------------------------------------
        $lldpOutput = $this->getLatestOutputs('lldp');
        if ($lldpOutput && !empty($lldpOutput->data)) {
            // LLDP neighbor blocks are also separated by lines of dashes
            $blocks = preg_split('/^-{3,}\s*$/m', $lldpOutput->data);
            foreach ($blocks as $block) {
                $block = trim($block);
                if (empty($block)) {
                    continue;
                }

                $entry = [];

                // System Name
                if (preg_match('/System Name:\s*(\S+)/i', $block, $m)) {
                    $entry['name'] = trim($m[1]);
                }

                // Chassis ID (MAC address) — may be colon- or dot-separated
                if (preg_match('/Chassis id:\s*([0-9a-fA-F:.\-]+)/i', $block, $m)) {
                    $entry['mac'] = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $m[1]));
                }

                // Local interface — expand abbreviation for consistent media map lookup
                if (preg_match('/Local Intf:\s*(\S+)/i', $block, $m)) {
                    $entry['local_port'] = $this->expandInterfaceName(trim($m[1]));
                }

                // Remote port — use Port id, expand abbreviation
                if (preg_match('/Port id:\s*(\S+)/i', $block, $m)) {
                    $entry['remote_port'] = $this->expandInterfaceName(trim($m[1]));
                }

                if (empty($entry['name'])) {
                    continue;
                }

                // Merge: LLDP overrides CDP for the same neighbor name
                $key      = strtolower($entry['name']);
                $existing = $neighbors[$key] ?? [];
                $neighbors[$key] = array_merge($existing, $entry);
            }
        }

        // ----------------------------------------------------------------
        // 4. Attach media_type from the interfaces map, then normalise
        // ----------------------------------------------------------------
        $result = [];
        foreach ($neighbors as $entry) {
            // Look up media type by the (expanded) local port name
            $localPort = $entry['local_port'] ?? null;
            if ($localPort && isset($mediaMap[$localPort])) {
                $entry['media_type'] = $mediaMap[$localPort];
            }

            // Enforce key order and strip null / empty-string values
            $normalized = array_filter(
                [
                    'name'        => $entry['name']        ?? null,
                    'mac'         => $entry['mac']          ?? null,
                    'local_port'  => $entry['local_port']   ?? null,
                    'remote_port' => $entry['remote_port']  ?? null,
                    'media_type'  => $entry['media_type']   ?? null,
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
