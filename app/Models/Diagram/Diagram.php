<?php

namespace App\Models\Diagram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Models\Mist\Site;
use App\Models\Netbox\DCIM\Sites as NetboxSites;
use App\Models\Netbox\DCIM\Devices as NetboxDevices;
use App\Models\Netbox\DCIM\VirtualChassis;
use App\Models\Device\Cisco\Cisco;
use App\Models\Device\Juniper\Juniper;

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

    protected static array $siteCache = [];
    protected static array $deviceCache = [];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Fetch (and memoize for the lifetime of the request) the Netbox site
     * record for a given site ID, so repeated lookups within one generate()
     * call don't each cost a Netbox HTTP round trip.
     *
     * @param  int  $netboxSiteId  Netbox site integer ID
     * @return NetboxSites
     * @throws \Exception if the Netbox site cannot be found
     */
    protected static function getNetboxSite(int $netboxSiteId): NetboxSites
    {
        if (!isset(static::$siteCache[$netboxSiteId])) {
            $site = NetboxSites::find($netboxSiteId);
            if (!isset($site->id)) {
                throw new \Exception("Netbox site with ID {$netboxSiteId} not found.");
            }
            static::$siteCache[$netboxSiteId] = $site;
        }
        return static::$siteCache[$netboxSiteId];
    }

    /**
     * Fetch (and memoize for the lifetime of the request) the full,
     * unfiltered list of Netbox devices at a site, so getNetboxDevices(),
     * getCiscoDevices(), and getJuniperDevices() can share a single fetch
     * instead of each issuing their own paginated Netbox HTTP call.
     *
     * @param  int  $netboxSiteId  Netbox site integer ID
     * @return Collection
     */
    protected static function getAllNetboxDevices(int $netboxSiteId): Collection
    {
        if (!isset(static::$deviceCache[$netboxSiteId])) {
            static::$deviceCache[$netboxSiteId] = NetboxDevices::where('site_id', $netboxSiteId)->get();
        }
        return static::$deviceCache[$netboxSiteId];
    }

    /**
     * Fetch all addressable devices at a Netbox site and return them as the
     * base node list for diagram generation.
     *
     * Two passes are made:
     *   1. Virtual Chassis — each VC is treated as a single logical device.
     *      The node's netbox_id is set to the master device's Netbox ID
     *      (falls back to the VC's own ID if no master is set).
     *   2. Standalone devices — Netbox devices that are NOT VC members.
     *
     * A node is only created when BOTH a name and an IP address are present.
     *
     * Returns an array with one key:
     *   - 'devices' : Collection of base node arrays, each containing:
     *       netbox_id, name, ip
     *
     * @param  int  $netboxSiteId  Netbox site integer ID
     * @return array{devices: Collection}
     * @throws \Exception if the Netbox site cannot be found
     */
    public static function getNetboxDevices(int $netboxSiteId): array
    {
        // 1. Look up the Netbox site
        $netboxSite = static::getNetboxSite($netboxSiteId);

        $devices = collect();

        // 2. Virtual Chassis — one logical node per VC
        $virtualChassisList = VirtualChassis::where('site_id', $netboxSiteId)->get();
        foreach ($virtualChassisList as $vc) {
            if (empty($vc->name)) {
                continue;
            }
            $ip = $vc->getIpAddress();
            if (!$ip) {
                continue;
            }
            // Use the master device's Netbox ID as the node's netbox_id so that
            // Cisco enrichment (which keys by netbox_id) can match correctly.
            $netboxId = isset($vc->master->id) ? $vc->master->id : $vc->id;
            $roleInfo = static::resolveRole($vc->name);

            $devices->push([
                'netbox_id' => $netboxId,
                'name'      => $vc->name,
                'ip'        => $ip,
                'role'      => $roleInfo['role'],
                'priority'  => $roleInfo['priority'],
            ]);
        }

        // 3. Standalone devices — exclude VC members
        $standaloneDevices = static::getAllNetboxDevices($netboxSiteId)
            ->filter(fn($d) => !isset($d->virtual_chassis->id));
        foreach ($standaloneDevices as $nbDevice) {
            if (empty($nbDevice->name)) {
                continue;
            }
            $ip = $nbDevice->getIpAddress();
            if (!$ip) {
                continue;
            }
            $roleInfo = static::resolveRole($nbDevice->name);

            $devices->push([
                'netbox_id' => $nbDevice->id,
                'name'      => $nbDevice->name,
                'ip'        => $ip,
                'role'      => $roleInfo['role'],
                'priority'  => $roleInfo['priority'],
            ]);
        }

        return [
            'devices' => $devices,
        ];
    }

    /**
     * Resolve a Netbox site ID to its matching Mist site, fetch all device stats,
     * and return enrichment data keyed by device name (lowercase).
     *
     * Returns an array with one key:
     *   - 'enrichments' : array keyed by device name (lowercase), each entry containing:
     *       mist_id, mac, vendor, model, role, priority, status
     *       [, version][, uptimeSeconds][, mgmtUrl], neighbors
     *
     * Each neighbor entry contains:
     *   name, mac, local_port, remote_port, media_type
     *
     * @param  int  $netboxSiteId  Netbox site integer ID
     * @return array{enrichments: array}
     * @throws \Exception if the Netbox site or matching Mist site cannot be found
     */
    public static function getMistDevices(int $netboxSiteId): array
    {
        // 1. Look up the Netbox site
        $netboxSite = static::getNetboxSite($netboxSiteId);

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

        // 6. Build enrichment map keyed by device name (lowercase)
        $enrichments = [];
        foreach ($enrichedDevices as $d) {
            if (!isset($d->id) || empty($d->name)) {
                continue;
            }

            $model    = $d->model ?? null;
            $status   = isset($d->status) && $d->status === 'connected' ? 'online' : 'offline';
            $roleInfo = static::resolveRole($d->name ?? '');

            $entry = [
                'name'     => $d->name,
                'mist_id'  => $d->id,
                'mac'      => isset($d->mac) ? preg_replace('/[^a-z0-9]/', '', strtolower($d->mac)) : null,
                'vendor'   => 'juniper',
                'model'    => $model,
                'role'     => $roleInfo['role'],
                'priority' => $roleInfo['priority'],
                'status'   => $status,
            ];

            if (isset($d->version)) {
                $entry['version'] = $d->version;
            }
            if (isset($d->uptime)) {
                $entry['uptimeSeconds'] = $d->uptime;
            }
            if (isset($d->id)) {
                $entry['mgmtUrl'] = 'https://manage.mist.com/admin/?org_id='
                    . Site::getOrgId() . '#!ap/' . $d->id;
            }

            // Build neighbors by traversing custom->vc_members->pics->ports
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
            $entry['neighbors'] = $neighbors;

            $enrichments[strtolower($d->name)] = $entry;
        }

        return [
            'enrichments' => $enrichments,
        ];
    }

    /**
     * Resolve a Netbox site ID to its Cisco devices stored in the local devices table,
     * call getNeighbors() on each, and return enrichment data keyed by netbox_id.
     *
     * Returns an array with one key:
     *   - 'enrichments' : array keyed by netbox_id (integer), each entry containing:
     *       netman2_id, vendor, model, status, neighbors
     *
     * Each neighbor entry contains:
     *   name, mac, local_port, remote_port, media_type
     *
     * Role/priority are intentionally NOT included here: they are already
     * resolved once in getNetboxDevices() from the authoritative Netbox device
     * name. Recomputing them from this device's locally-collected hostname
     * (Cisco::getName(), which depends on run data having been collected) would
     * let a missing/stale local hostname silently overwrite a correct role via
     * the array_merge() in generate().
     *
     * @param  int  $netboxSiteId  Netbox site integer ID
     * @return array{enrichments: array}
     * @throws \Exception if the Netbox site cannot be found
     */
    public static function getCiscoDevices(int $netboxSiteId): array
    {
        // 1. Look up the Netbox site
        $netboxSite = static::getNetboxSite($netboxSiteId);

        // 2. Fetch all Netbox devices at this site
        $netboxDevices = static::getAllNetboxDevices($netboxSiteId);

        $enrichments = [];

        foreach ($netboxDevices as $nbDevice) {
            // Find the local Cisco model record linked to this Netbox device
            $ciscoDevice = Cisco::where('netbox_id', $nbDevice->id)->first();
            if (!$ciscoDevice) {
                continue;
            }

            $model = $ciscoDevice->getModel();

            $entry = [
                'netman_id'  => $ciscoDevice->id,
                'vendor'     => 'cisco',
                'model'      => $model,
                'status'     => 'online',
                'neighbors'  => $ciscoDevice->getNeighbors(),
            ];

            // Key by the Netbox device ID so generate() can match by netbox_id
            $enrichments[$nbDevice->id] = $entry;
        }

        return [
            'enrichments' => $enrichments,
        ];
    }

    /**
     * Resolve a Netbox site ID to its Juniper devices stored in the local devices table,
     * call getNeighbors() on each, and return enrichment data keyed by netbox_id.
     *
     * Returns an array with one key:
     *   - 'enrichments' : array keyed by netbox_id (integer), each entry containing:
     *       netman_id, vendor, model, status, neighbors
     *
     * Each neighbor entry contains:
     *   name, mac, local_port, remote_port
     *   (media_type is currently omitted by Juniper::getNeighbors())
     *
     * Role/priority are intentionally NOT included here: they are already
     * resolved once in getNetboxDevices() from the authoritative Netbox device
     * name. Recomputing them from this device's locally-collected hostname
     * (Juniper::getName(), which depends on run data having been collected)
     * would let a missing/stale local hostname silently overwrite a correct
     * role via the array_merge() in generate().
     *
     * @param  int  $netboxSiteId  Netbox site integer ID
     * @return array{enrichments: array}
     * @throws \Exception if the Netbox site cannot be found
     */
    public static function getJuniperDevices(int $netboxSiteId): array
    {
        // 1. Look up the Netbox site
        $netboxSite = static::getNetboxSite($netboxSiteId);

        // 2. Fetch all Netbox devices at this site
        $netboxDevices = static::getAllNetboxDevices($netboxSiteId);

        $enrichments = [];

        foreach ($netboxDevices as $nbDevice) {
            // Find the local Juniper model record linked to this Netbox device
            $juniperDevice = Juniper::where('netbox_id', $nbDevice->id)->first();
            if (!$juniperDevice) {
                continue;
            }

            $model = $juniperDevice->getModel();

            $entry = [
                'netman_id'  => $juniperDevice->id,
                'vendor'     => 'juniper',
                'model'      => $model,
                'status'     => 'online',
                'neighbors'  => $juniperDevice->getNeighbors2(),
            ];

            // Key by the Netbox device ID so generate() can match by netbox_id
            $enrichments[$nbDevice->id] = $entry;
        }

        return [
            'enrichments' => $enrichments,
        ];
    }

    /**
     * Generate a full diagram payload for a given Netbox site ID.
     *
     * 1. Builds a base node list from getNetboxDevices() (netbox_id, name, ip).
     * 2. Enriches nodes with Mist data from getMistDevices() (matched by name).
     * 3. Enriches nodes with Cisco data from getCiscoDevices() (matched by netbox_id).
     * 4. Enriches nodes with Juniper data from getJuniperDevices() (matched by netbox_id).
     * 5. Builds LLDP-based links across the full node set.
     * 6. Assembles and returns the final diagram structure.
     *
     * @param  int  $netboxSiteId  Netbox site integer ID
     * @return array{sitecode: string, nodes: array, links: array, unlinked: array}
     * @throws \Exception if the Netbox site cannot be found
     */
    public static function generate(int $netboxSiteId): array
    {
        // 1. Look up the Netbox site (needed for sitecode in the return payload)
        $netboxSite = static::getNetboxSite($netboxSiteId);

        // 2. Build the base node list from Netbox (netbox_id, name, ip)
        $netboxResult = static::getNetboxDevices($netboxSiteId);
        $allDevices   = $netboxResult['devices'];

        // Build a name (lowercase) -> collection index map for enrichment merging
        $nameToIndex     = [];
        $netboxIdToIndex = [];
        foreach ($allDevices as $index => $node) {
            if (!empty($node['name'])) {
                $nameToIndex[strtolower($node['name'])] = $index;
            }
            if (!empty($node['netbox_id'])) {
                $netboxIdToIndex[$node['netbox_id']] = $index;
            }
        }
/*
        // 3. Enrich nodes with Mist data (matched by device name)
        //    If no existing node matches, create a new node from the Mist data (no netbox_id).
        try {
            $mistResult = static::getMistDevices($netboxSiteId);
            foreach ($mistResult['enrichments'] as $nameLower => $enrichment) {
                if (isset($nameToIndex[$nameLower])) {
                    // Merge into existing Netbox node
                    $idx              = $nameToIndex[$nameLower];
                    $allDevices[$idx] = array_merge($allDevices[$idx], $enrichment);
                } else {
                    // No Netbox node exists — create a new node from Mist data alone
                    $newNode = $enrichment;
                    // Ensure 'name' is set (enrichment key is lowercase; use the enrichment's name field if present)
                    if (empty($newNode['name'])) {
                        $newNode['name'] = $nameLower;
                    }
                    $newIndex                  = $allDevices->count();
                    $nameToIndex[$nameLower]   = $newIndex;
                    $allDevices->push($newNode);
                }
            }
        } catch (\Exception $e) {
            // Mist may not be configured for this site — continue without it
        }
/**/
        // 4. Enrich nodes with Cisco data (matched by netbox_id)
        try {
            $ciscoResult = static::getCiscoDevices($netboxSiteId);
            foreach ($ciscoResult['enrichments'] as $netboxId => $enrichment) {
                if (!isset($netboxIdToIndex[$netboxId])) {
                    continue;
                }
                $idx              = $netboxIdToIndex[$netboxId];
                $allDevices[$idx] = array_merge($allDevices[$idx], $enrichment);
            }
        } catch (\Exception $e) {
            // No Cisco devices found — continue without them
        }

        // 5. Enrich nodes with Juniper data (matched by netbox_id)
        try {
            $juniperResult = static::getJuniperDevices($netboxSiteId);
            foreach ($juniperResult['enrichments'] as $netboxId => $enrichment) {
                if (!isset($netboxIdToIndex[$netboxId])) {
                    continue;
                }
                $idx              = $netboxIdToIndex[$netboxId];
                $allDevices[$idx] = array_merge($allDevices[$idx], $enrichment);
            }
        } catch (\Exception $e) {
            // No Juniper devices found — continue without them
        }

        // Future getter methods can be added here, e.g.:
        // try {
        //     $otherResult = static::getOtherDevices($netboxSiteId);
        //     foreach ($otherResult['enrichments'] as $key => $enrichment) { ... }
        // } catch (\Exception $e) {}

        // 6. Build LLDP links from the full enriched device list
        $links = static::buildLinks($allDevices);

        // 7. Build nodes array from the compiled device collection
        $nodes = $allDevices->values()->all();

        // 8. Determine unlinked devices (appear in neither source nor target of any link)
        $linkedDeviceNames = [];
        foreach ($links as $link) {
            $linkedDeviceNames[$link['source']['deviceName']] = true;
            $linkedDeviceNames[$link['target']['deviceName']] = true;
        }

        $unlinked = [];
        foreach ($allDevices as $device) {
            $name = $device['name'];
            if (!isset($linkedDeviceNames[$name])) {
                $unlinked[] = $name;
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
     * @return array          ['role' => string, 'priority' => int|null]
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
            'Console Server'  => ['priority' => 100, 'names' => ['OOB']],
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
     *   - 'name'      : device name (used to resolve neighbor device names)
     *   - 'neighbors' : array of neighbor entries, each with:
     *       - 'name'        : peer device name (normalized, lowercase)
     *       - 'mac'         : peer device MAC (normalized, lowercase alphanumeric)
     *       - 'local_port'  : local port name
     *       - 'remote_port' : remote port name on the peer
     *       - 'media_type'  : physical media type
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

        // Build name -> name lookup map and mac -> name lookup map from the normalized device nodes.
        $nameSet   = [];
        $macToName = [];
        foreach ($deviceList as $device) {
            if (empty($device['name'])) {
                continue;
            }
            $nameSet[strtolower($device['name'])] = $device['name'];
            if (!empty($device['mac'])) {
                $macToName[preg_replace('/[^a-z0-9]/', '', strtolower($device['mac']))] = $device['name'];
            }
        }

        // Supplement mac -> name from neighbor entries: a neighbor's mac can be cross-
        // referenced to a device name via the name set already built above.
        foreach ($deviceList as $device) {
            if (!isset($device['neighbors']) || !is_array($device['neighbors'])) {
                continue;
            }
            foreach ($device['neighbors'] as $neighbor) {
                if (!empty($neighbor['mac']) && !empty($neighbor['name'])) {
                    $peerNameKey = strtolower($neighbor['name']);
                    if (isset($nameSet[$peerNameKey]) && !isset($macToName[$neighbor['mac']])) {
                        $macToName[$neighbor['mac']] = $nameSet[$peerNameKey];
                    }
                }
            }
        }

        foreach ($deviceList as $device) {
            if (empty($device['name']) || !isset($device['neighbors']) || !is_array($device['neighbors'])) {
                continue;
            }

            $sourceDeviceName = $device['name'];

            foreach ($device['neighbors'] as $neighbor) {
                // Resolve target device name by name first, then fall back to MAC
                $targetDeviceName = null;
                if (!empty($neighbor['name'])) {
                    $targetDeviceName = $nameSet[strtolower($neighbor['name'])] ?? null;
                }
                if (!$targetDeviceName && !empty($neighbor['mac'])) {
                    $targetDeviceName = $macToName[$neighbor['mac']] ?? null;
                }

                if (!$targetDeviceName) {
                    continue;
                }

                $sourcePort = $neighbor['local_port']  ?? null;
                $targetPort = $neighbor['remote_port'] ?? null;

                // Deduplicate: treat A->B and B->A as the same link. Key on the pair of
                // (device, port) endpoints rather than device names alone, so that when
                // traversed from the other side (source/target and their ports swapped),
                // the sorted endpoint pair still comes out identical.
                $sourceEndpoint = $sourceDeviceName . '#' . ($sourcePort ?? '');
                $targetEndpoint = $targetDeviceName . '#' . ($targetPort ?? '');
                $pairKey = implode('|', [
                    min($sourceEndpoint, $targetEndpoint),
                    max($sourceEndpoint, $targetEndpoint),
                ]);

                if (isset($seenLinks[$pairKey])) {
                    continue;
                }
                $seenLinks[$pairKey] = true;

                $medium = $neighbor['media_type'] ?? 'unknown';

                $links[] = [
                    'id'            => 'link-' . $linkCounter++,
                    'source'        => [
                        'deviceName' => $sourceDeviceName,
                        'port'       => $sourcePort,
                    ],
                    'target'        => [
                        'deviceName' => $targetDeviceName,
                        'port'       => $targetPort,
                    ],
                    'medium'        => $medium,
                ];
            }
        }

        return $links;
    }

}
