<?php

namespace App\Models\Diagram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Models\Mist\Site;
use App\Models\Netbox\DCIM\Sites as NetboxSites;

class Diagram extends Model
{
    protected $table = 'diagrams';

    protected $fillable = [
        'name',
        'type',
        'site_id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Resolve a Netbox site ID to its matching Mist site, fetch all device stats,
     * and normalize each device into the standard format with neighbor information.
     *
     * Returns an array with one key:
     *   - 'devices' : Collection of normalized device arrays
     *
     * Normalized device format:
     *   id, name, vendor, model, role, status[, ip][, version][, uptimeSeconds][, mgmtUrl], neighbors
     *
     * Each neighbor entry contains:
     *   name, mac, local_port, remote_port
     *
     * @param  int  $netboxSiteId  Netbox site integer ID
     * @return array{devices: Collection}
     * @throws \Exception if the Netbox site or matching Mist site cannot be found
     */
    public static function getMistDevices(int $netboxSiteId): array
    {
        // 1. Look up the Netbox site
        $netboxSite = NetboxSites::find($netboxSiteId);
        if (!isset($netboxSite->id)) {
            throw new \Exception("Netbox site with ID {$netboxSiteId} not found.");
        }

        // 2. Find the matching Mist site by name
        $mistSite = $netboxSite->getMistSite();
        if (!isset($mistSite->id)) {
            throw new \Exception("No Mist site found matching Netbox site name '{$netboxSite->name}'.");
        }

        // 3. Fetch basic device list and filter to switches and gateways only
        $mistDevices = $mistSite->getDevices()
            ->filter(fn($d) => in_array($d->type ?? null, ['switch', 'gateway']));

        // 4. Enrich each device with full stats via getSummaryDetails()
        //    This populates $device->custom->vc_members[].pics[].ports[] with
        //    per-port detail including neighbor_system_name, neighbor_mac,
        //    neighbor_port_description, and port_id.
        $enrichedDevices = $mistDevices->map(fn($d) => $d->getSummaryDetails());

        // 5. Build a normalized MAC -> name lookup map for neighbor resolution
        //    MAC is normalized: lowercase, alphanumeric only
        $macToName = [];
        foreach ($enrichedDevices as $d) {
            if (isset($d->mac) && isset($d->name)) {
                $normalizedMac             = preg_replace('/[^a-z0-9]/', '', strtolower($d->mac));
                $macToName[$normalizedMac] = strtolower($d->name);
            }
        }

        // 6. Normalize each enriched device into the standard output format
        $devices = $enrichedDevices
            ->filter(fn($d) => isset($d->id))
            ->map(function ($d) use ($macToName) {
                $model    = $d->model ?? null;
                $status   = isset($d->status) && $d->status === 'connected' ? 'online' : 'offline';
                $roleInfo = static::resolveRole($d->name ?? '');

                $node = [
                    'id'       => $d->id,
                    'name'     => $d->name ?? null,
                    'mac'      => isset($d->mac) ? preg_replace('/[^a-z0-9]/', '', strtolower($d->mac)) : null,
                    'vendor'   => 'juniper',
                    'model'    => $model,
                    'role'     => $roleInfo['role'],
                    'priority' => $roleInfo['priority'],
                    'status'   => $status,
                ];

                if (isset($d->ip_stat->ip)) {
                    $node['ip'] = $d->ip_stat->ip;
                }
                if (isset($d->version)) {
                    $node['version'] = $d->version;
                }
                if (isset($d->uptime)) {
                    $node['uptimeSeconds'] = $d->uptime;
                }
                if (isset($d->id)) {
                    $node['mgmtUrl'] = 'https://manage.mist.com/admin/?org_id='
                        . Site::getOrgId() . '#!ap/' . $d->id;
                }

                // Build neighbors by traversing custom->vc_members->pics->ports
                // Each port with a neighbor_mac set represents an LLDP neighborship.
                $neighbors = [];
                if (isset($d->custom->vc_members) && is_array($d->custom->vc_members)) {
                    foreach ($d->custom->vc_members as $vcMember) {
                        if (!isset($vcMember->pics) || !is_array($vcMember->pics)) {
                            continue;
                        }
                        foreach ($vcMember->pics as $pic) {
                            if (!isset($pic->ports) || !is_array($pic->ports)) {
                                continue;
                            }
                            foreach ($pic->ports as $port) {
                                if (!isset($port->neighbor_mac) || empty($port->neighbor_mac)) {
                                    continue;
                                }
                                $peerMacNorm = preg_replace('/[^a-z0-9]/', '', strtolower($port->neighbor_mac));
                                $neighbors[] = [
                                    'name'        => isset($port->neighbor_system_name)
                                                        ? strtolower($port->neighbor_system_name)
                                                        : ($macToName[$peerMacNorm] ?? null),
                                    'mac'         => $peerMacNorm,
                                    'local_port'  => $port->port_id ?? null,
                                    'remote_port' => $port->neighbor_port_desc ?? null,
                                    'media_type'  => $port->media_type ?? null,
                                ];
                            }
                        }
                    }
                }
                $node['neighbors'] = $neighbors;

                return $node;
            })->values();

        return [
            'devices' => $devices,
        ];
    }

    /**
     * Generate a full diagram payload for a given Netbox site ID.
     *
     * Calls all getter methods to obtain normalized devices with neighbor info,
     * compiles the full device list, builds LLDP-based links across the entire
     * compiled set, then assembles the final diagram structure.
     *
     * @param  int  $netboxSiteId  Netbox site integer ID
     * @return array{sitecode: string, nodes: array, links: array, unlinked: array}
     * @throws \Exception if the Netbox site or matching Mist site cannot be found
     */
    public static function generate(int $netboxSiteId): array
    {
        // 1. Look up the Netbox site (needed for sitecode in the return payload)
        $netboxSite = NetboxSites::find($netboxSiteId);
        if (!isset($netboxSite->id)) {
            throw new \Exception("Netbox site with ID {$netboxSiteId} not found.");
        }

        // 2. Run all getter methods and compile devices into one collection
        $allDevices = collect();

        $mistResult = static::getMistDevices($netboxSiteId);
        $allDevices = $allDevices->merge($mistResult['devices']);

        // Future getter methods can be added here, e.g.:
        // $otherResult = static::getOtherDevices($netboxSiteId);
        // $allDevices  = $allDevices->merge($otherResult['devices']);

        // 3. Build LLDP links from the full compiled device list
        $links = static::buildLinks($allDevices);

        // 4. Build nodes array from the compiled device collection
        $nodes = $allDevices->values()->all();

        // 5. Determine unlinked devices (appear in neither source nor target of any link)
        $linkedDeviceIds = [];
        foreach ($links as $link) {
            $linkedDeviceIds[$link['source']['deviceId']] = true;
            $linkedDeviceIds[$link['target']['deviceId']] = true;
        }

        $unlinked = [];
        foreach ($allDevices as $device) {
            $id = $device['id'];
            if (!isset($linkedDeviceIds[$id])) {
                $unlinked[] = $id;
            }
        }

        return [
            'sitecode' => $netboxSite->name,
            'nodes'    => $nodes,
            'links'    => $links,
            'unlinked' => $unlinked,
        ];
    }

    // -------------------------------------------------------------------------
    // Protected helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve a human-readable role from a device name using the 3-character
     * device type token located at position 8 in the name string.
     *
     * Device name format: SSSSSSSSTTTNNN[N]
     *   - S (8 chars) : site code
     *   - T (3 chars) : device type token  <-- extracted here
     *   - N (2-4 chars): numeric device ID
     *
     * The role map is defined as role => [tokens], allowing multiple type tokens
     * to map to the same role. Add new tokens to any role array as needed.
     *
     * @param  string  $name  Device name
     * @return string         Human-readable role, or 'unknown' if no match
     */
    protected static function resolveRole(string $name): array
    {
        // Extract the 3-character device type token starting at position 8
        $typeToken = strtoupper(substr($name, 8, 3));

        // Role => ['priority' => int, 'names' => [tokens]]
        // Multiple tokens can map to the same role — add to 'names' as needed.
        $roleMap = [
            'Core Router'     => ['priority' => 10, 'names' => ['PER']],
            'Router'          => ['priority' => 20, 'names' => ['RWA']],
            'Distribution'    => ['priority' => 30, 'names' => ['SWD']],
            'Aggregation'     => ['priority' => 40, 'names' => ['AGG']],
            'Access'          => ['priority' => 50, 'names' => ['SWA', 'SWM']],
            'Wireless Bridge' => ['priority' => 60, 'names' => ['WBR']],
        ];

        // Invert to token => ['role' => ..., 'priority' => ...] for O(1) lookup
        $tokenToRole = [];
        foreach ($roleMap as $role => $config) {
            foreach ($config['names'] as $token) {
                $tokenToRole[$token] = ['role' => $role, 'priority' => $config['priority']];
            }
        }

        return $tokenToRole[$typeToken] ?? ['role' => 'unknown', 'priority' => null];
    }

    /**
     * Build LLDP-based links from a collection of normalized device nodes.
     *
     * Each device node is expected to have:
     *   - 'id'        : unique device identifier
     *   - 'name'      : device name (used to resolve neighbor device IDs)
     *   - 'neighbors' : array of neighbor entries, each with:
     *       - 'name'        : peer device name (normalized, lowercase)
     *       - 'mac'         : peer device MAC (normalized, lowercase alphanumeric)
     *       - 'local_port'  : local port name
     *       - 'remote_port' : remote port name on the peer
     *
     * Bidirectional pairs are deduplicated so each physical link appears once.
     *
     * @param  iterable  $devices  Normalized device node arrays
     * @return array
     */
    protected static function buildLinks(iterable $devices): array
    {
        $links       = [];
        $seenLinks   = [];
        $linkCounter = 1;

        // Convert to array once so we can iterate multiple times safely
        $deviceList = is_array($devices) ? $devices : iterator_to_array($devices, false);

        // Build name -> id lookup map from the normalized device nodes.
        // Also build mac -> id from each device's own 'mac' field (if present).
        $nameToId = [];
        $macToId  = [];
        foreach ($deviceList as $device) {
            if (!isset($device['id'])) {
                continue;
            }
            if (!empty($device['name'])) {
                $nameToId[strtolower($device['name'])] = $device['id'];
            }
            if (!empty($device['mac'])) {
                $macToId[preg_replace('/[^a-z0-9]/', '', strtolower($device['mac']))] = $device['id'];
            }
        }

        // Supplement mac -> id from neighbor entries: a neighbor's mac can be cross-
        // referenced to a device ID via the name->id map already built above.
        foreach ($deviceList as $device) {
            if (!isset($device['neighbors']) || !is_array($device['neighbors'])) {
                continue;
            }
            foreach ($device['neighbors'] as $neighbor) {
                if (!empty($neighbor['mac']) && !empty($neighbor['name'])) {
                    $peerId = $nameToId[strtolower($neighbor['name'])] ?? null;
                    if ($peerId && !isset($macToId[$neighbor['mac']])) {
                        $macToId[$neighbor['mac']] = $peerId;
                    }
                }
            }
        }

        foreach ($deviceList as $device) {
            if (!isset($device['id']) || !isset($device['neighbors']) || !is_array($device['neighbors'])) {
                continue;
            }

            $sourceDeviceId = $device['id'];

            foreach ($device['neighbors'] as $neighbor) {
                // Resolve target device ID by name first, then fall back to MAC
                $targetDeviceId = null;
                if (!empty($neighbor['name'])) {
                    $targetDeviceId = $nameToId[strtolower($neighbor['name'])] ?? null;
                }
                if (!$targetDeviceId && !empty($neighbor['mac'])) {
                    $targetDeviceId = $macToId[$neighbor['mac']] ?? null;
                }

                if (!$targetDeviceId) {
                    continue;
                }

                $sourcePort = $neighbor['local_port']  ?? null;
                $targetPort = $neighbor['remote_port'] ?? null;

                // Deduplicate: treat A->B and B->A as the same link per port pair
                $pairKey = implode('|', [
                    min($sourceDeviceId, $targetDeviceId),
                    max($sourceDeviceId, $targetDeviceId),
                    $sourcePort ?? '',
                ]);

                if (isset($seenLinks[$pairKey])) {
                    continue;
                }
                $seenLinks[$pairKey] = true;

                $medium = $neighbor['media_type'] ?? 'unknown';

                $links[] = [
                    'id'            => 'link-' . $linkCounter++,
                    'source'        => [
                        'deviceId'  => $sourceDeviceId,
                        'port'      => $sourcePort,
                    ],
                    'target'        => [
                        'deviceId'  => $targetDeviceId,
                        'port'      => $targetPort,
                    ],
                    'medium'        => $medium,
                    'discoveredVia' => 'lldp',
                ];
            }
        }

        return $links;
    }

}
