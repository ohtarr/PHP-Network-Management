<?php

namespace App\Models\Device\Cisco\IOSXR;

use App\Models\Device\Cisco\Cisco;

class CiscoIOSXR extends Cisco
{
    protected static $singleTableSubclasses = [
    ];
    
    protected static $singleTableType = __CLASS__;

    //List of outputs to collect during a scan of this device.
    public $scan_outputs = [
        'run'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'show run',
        ],
        'version'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'show version',
        ],
        'interfaces'    =>  [
            'method'    =>  'ssh',
            'input'     =>  'show interfaces',
        ],
        'inventory'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'admin show inventory',
        ],
        'dir'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'dir',
        ],
        'cdp'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'show cdp neighbor detail',
        ],
        'lldp'          =>  [
            'method'    =>  'ssh',
            'input'     =>  'show lldp neighbor detail',
        ],
        'arp'           =>  [
            'method'    =>  'ssh',
            'input'     =>  'show arp',
        ],
        'arpv101'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'show arp vrf V101:DATACENTER',
        ],
        'arpv102'       =>  [
            'method'    =>  'ssh',
            'input'     =>  'show arp vrf V102:OFFICE',
        ],
        'route'         =>  [
            'method'    =>  'ssh',
            'input'     =>  'show ip route',
        ],
        'routev101'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'show ip route vrf V101:DATACENTER',
        ],
        'routev102'     =>  [
            'method'    =>  'ssh',
            'input'     =>  'show ip route vrf V102:OFFICE',
        ],
    ];
    /*
     Find the NAME of this device from OUTPUTs.
     Returns string (device name).
     */
    public function getName()
    {
        $reg = "/hostname (\S+)/";
        $output = $this->getLatestOutputs('run');
        if (!$output || empty($output->data)) {
            return null;
        }
        if (preg_match($reg, $output->data, $hits)) {
            return $hits[1];
        }
    }

    /*
     Find the serial of this device from OUTPUTs.
     Returns string (device serial).
     */
    public function getSerial()
    {
        //Reg to grab the serial from the show inventory.
        $reg = "/SN:\s+(\S+)/";
        $output = $this->getLatestOutputs('inventory');
        if (!$output || empty($output->data)) {
            return null;
        }
        if (preg_match($reg, $output->data, $hits)) {
            return $hits[1];
        }
    }

    /*
    Find the model of this device from OUTPUTs.
    Returns string (device model).
    */
    public function getModel()
    {
        //Reg to grab the model from the show inventory.
        $reg = "/PID:\s+(\S+)/";
        $output = $this->getLatestOutputs('inventory');
        if (!$output || empty($output->data)) {
            return null;
        }
        if (preg_match($reg, $output->data, $hits)) {
            return $hits[1];
        }
    }

    /*
     Find the MAC address of this device from OUTPUTs.
     Prefers the management interface (MgmtEth*); falls back to the first
     interface with a MAC in the latest 'show interfaces' output.
     Returns string (lowercase, no separators) or null.
     */
    public function getMac()
    {
        $output = $this->getLatestOutputs('interfaces');
        if (!$output || empty($output->data)) {
            return null;
        }

        $currentIf = null;
        $macs = [];
        foreach (explode("\n", $output->data) as $line) {
            if (preg_match('/^\s*(\S+)\s+is\s+(?:administratively\s+)?(?:up|down)/i', $line, $m)) {
                $currentIf = $m[1];
                continue;
            }
            if ($currentIf && !isset($macs[$currentIf]) && preg_match('/address is\s+([0-9a-fA-F.:\-]+)/i', $line, $m)) {
                $macs[$currentIf] = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $m[1]));
            }
        }

        if (empty($macs)) {
            return null;
        }

        foreach ($macs as $ifName => $mac) {
            if (stripos($ifName, 'MgmtEth') === 0) {
                return $mac;
            }
        }

        return reset($macs);
    }

    /*
    Determine the neighbors of this IOS-XR device by parsing the latest stored CDP and LLDP outputs.
    Also consults the latest 'interfaces' output to resolve the media type (copper/fiber/etc.) for
    each local port.

    IOS-XR differences from IOS/IOS-XE that this override handles:
      CDP  — Interface and Port ID appear on separate lines (not combined on one line).
      LLDP — Local interface is labelled "Local Interface:" instead of "Local Intf:".
             "Peer MAC Address:" is captured as a MAC fallback alongside "Chassis id:".

    CDP entries are collected first (no MAC available from CDP).
    LLDP entries are then merged in, keyed by neighbor NAME — LLDP wins for any field it provides
    because it carries the neighbor MAC address. Port names from both protocols are expanded to their
    full canonical form via expandInterfaceName() before being used as lookup keys.

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
        //
        //    IOS-XR interface headers are indented (leading whitespace) and
        //    do not contain a "Media Type is" line. Instead, the transceiver
        //    standard appears as the third comma-separated token on the
        //    duplex/speed line, e.g.:
        //      Full-duplex, 10000Mb/s, 10GBASE-SR, link type is force-up
        // ----------------------------------------------------------------
        $mediaMap = [];
        $interfacesOutput = $this->getLatestOutputs('interfaces');
        if ($interfacesOutput && !empty($interfacesOutput->data)) {
            // Linear pass: track the current interface name, then capture the
            // transceiver standard from the duplex/speed line.
            // This approach is robust to indentation variations and blank-line
            // stripping applied by applyLineFilters() during scan().
            //
            // IOS-XR interface header (may be indented):
            //   TenGigE0/0/0/34 is up, line protocol is up
            // IOS-XR duplex/speed line:
            //   Full-duplex, 10000Mb/s, 10GBASE-SR, link type is force-up
            $currentIf = null;
            foreach (explode("\n", $interfacesOutput->data) as $line) {
                // Detect an interface header line (works regardless of indentation)
                if (preg_match('/^\s*(\S+)\s+is\s+(?:up|down)/i', $line, $m)) {
                    $currentIf = $this->expandInterfaceName($m[1]);
                }
                // Capture the transceiver standard from the duplex/speed line
                if ($currentIf && preg_match('/Full-duplex,\s+\S+,\s+(\S+),\s+link type/i', $line, $m)) {
                    $normalized = $this->normalizeMediaType($m[1]);
                    if ($normalized !== null) {
                        $mediaMap[$currentIf] = $normalized;
                    }
                    $currentIf = null; // reset — no need to keep scanning this block
                }
            }
        }

        // ----------------------------------------------------------------
        // 2. Parse CDP output  (show cdp neighbor detail)
        //    IOS-XR: Interface and Port ID are on separate lines.
        //    Keyed by neighbor name (lowercased).
        // ----------------------------------------------------------------
        $neighbors = [];

        $cdpOutput = $this->getLatestOutputs('cdp');
        if ($cdpOutput && !empty($cdpOutput->data)) {
            $blocks = preg_split('/^-{3,}\s*$/m', $cdpOutput->data);
            foreach ($blocks as $block) {
                $block = trim($block);
                if (empty($block)) {
                    continue;
                }

                $entry = [];

                // Device ID — strip FQDN suffix and any trailing parenthetical
                if (preg_match('/^Device ID:\s*(\S+)/m', $block, $m)) {
                    $name = preg_replace('/\(.*\)$/', '', trim($m[1]));
                    $entry['name'] = explode('.', $name)[0];
                }

                // Local interface — on its own line on IOS-XR
                if (preg_match('/^Interface:\s*(\S+)/m', $block, $m)) {
                    $entry['local_port'] = $this->expandInterfaceName(trim($m[1]));
                }

                // Remote port — on its own line on IOS-XR
                if (preg_match('/^Port ID \(outgoing port\):\s*(\S+)/m', $block, $m)) {
                    $entry['remote_port'] = $this->expandInterfaceName(trim($m[1]));
                }

                if (empty($entry['name'])) {
                    continue;
                }

                $neighbors[strtolower($entry['name'])] = $entry;
            }
        }

        // ----------------------------------------------------------------
        // 3. Parse LLDP output  (show lldp neighbor detail)
        //    IOS-XR: local interface is "Local Interface:" not "Local Intf:".
        //            "Peer MAC Address:" is captured as a MAC source.
        //    Merge into the CDP-keyed array by neighbor name.
        //    LLDP fields override CDP fields when both are present.
        // ----------------------------------------------------------------
        $lldpOutput = $this->getLatestOutputs('lldp');
        if ($lldpOutput && !empty($lldpOutput->data)) {
            $blocks = preg_split('/^-{3,}\s*$/m', $lldpOutput->data);
            foreach ($blocks as $block) {
                $block = trim($block);
                if (empty($block)) {
                    continue;
                }

                $entry = [];

                // System Name
                if (preg_match('/System Name:\s*(\S+)/i', $block, $m)) {
                    $entry['name'] = explode('.', trim($m[1]))[0];
                }

                // Chassis ID (MAC address) — may be colon- or dot-separated
                if (preg_match('/Chassis id:\s*([0-9a-fA-F:.\-]+)/i', $block, $m)) {
                    $entry['mac'] = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $m[1]));
                }

                // Peer MAC Address — IOS-XR specific; use as fallback if Chassis id not present
                if (empty($entry['mac']) && preg_match('/Peer MAC Address:\s*([0-9a-fA-F:.\-]+)/i', $block, $m)) {
                    $entry['mac'] = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $m[1]));
                }

                // Local interface — IOS-XR uses "Local Interface:" instead of "Local Intf:"
                if (preg_match('/Local Interface:\s*(\S+)/i', $block, $m)) {
                    $entry['local_port'] = $this->expandInterfaceName(trim($m[1]));
                }

                // Remote port — Port id (same as IOS/IOS-XE)
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
            $localPort = $entry['local_port'] ?? null;
            if ($localPort && isset($mediaMap[$localPort])) {
                $entry['media_type'] = $mediaMap[$localPort];
            }

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
