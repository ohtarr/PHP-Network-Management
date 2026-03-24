<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Manufacturers;
use App\Models\LibreNMS\Device as LibreDevice;
use App\Models\LibreNMS\Location as LibreLocation;
use App\Models\LibreNMS\DeviceGroup;
use JJG\Ping;

class SyncDeviceLibreNMSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries   = 1;

    /**
     * The Netbox device ID to sync into LibreNMS.
     */
    public int $netboxDeviceId;

    /**
     * Create a new job instance.
     *
     * @param  int  $netboxDeviceId  The Netbox DCIM device ID
     */
    public function __construct(int $netboxDeviceId)
    {
        $this->netboxDeviceId = $netboxDeviceId;
    }

    /**
     * Execute the job.
     *
     * Mirrors the per-device logic from syncLibreNMS but scoped to a single
     * Netbox device.  Steps performed:
     *
     *  1. Fetch the Netbox device and determine its DNS hostname (generated name).
     *  2. Ensure the LibreNMS Location for the device's site exists (create if not).
     *  3. Ensure a SITE_<sitecode> DeviceGroup exists in LibreNMS (create if not).
     *  4. If the device is not yet in LibreNMS:
     *       - Ping the hostname first; skip if unreachable.
     *       - Add it, setting location and SNMP-disable flag for Opengear devices.
     *  5. If the device is already in LibreNMS:
     *       - Sync the ignore/alerting flag from Netbox custom_fields->ALERT.
     *       - Sync the location_id if it has drifted.
     */
    public function handle(): void
    {
        Log::info('SyncDeviceLibreNMSJob', [
            'state'            => 'starting',
            'netbox_device_id' => $this->netboxDeviceId,
        ]);

        // ── 1. Fetch the Netbox device ────────────────────────────────────────
        $device = Devices::find($this->netboxDeviceId);

        if (!isset($device->id)) {
            Log::error('SyncDeviceLibreNMSJob: Netbox device not found', [
                'netbox_device_id' => $this->netboxDeviceId,
            ]);
            return;
        }

        if (!isset($device->name) || !$device->name) {
            Log::warning('SyncDeviceLibreNMSJob: device has no name, skipping', [
                'netbox_device_id' => $this->netboxDeviceId,
            ]);
            return;
        }

        // Respect the POLLING custom field — if false, nothing to do.
        if (isset($device->custom_fields->POLLING) && $device->custom_fields->POLLING === false) {
            Log::info('SyncDeviceLibreNMSJob: POLLING is disabled on device, skipping', [
                'name' => $device->name,
            ]);
            return;
        }

        // Skip non-master VC members — the chassis entry belongs to the master.
        $isVcMember = isset($device->virtual_chassis->id);
        $isVcMaster = $isVcMember
            && isset($device->virtual_chassis->master->id)
            && $device->virtual_chassis->master->id === $device->id;

        if ($isVcMember && !$isVcMaster) {
            Log::info('SyncDeviceLibreNMSJob: device is a non-master VC member, skipping', [
                'name' => $device->name,
            ]);
            return;
        }

        // Determine the DNS hostname LibreNMS should use.
        $isOpengear  = $this->isOpengear($device);
        $hostname    = $device->generateDnsName();
        if ($isOpengear) {
            $hostname .= '-oob';
        }

        Log::info('SyncDeviceLibreNMSJob: processing device', [
            'name'       => $device->name,
            'id'         => $device->id,
            'hostname'   => $hostname,
            'is_opengear'=> $isOpengear,
        ]);

        // ── 2. Ensure LibreNMS Location exists for the device's site ──────────
        $libreLocation = null;
        if (isset($device->site->name) && $device->site->name) {
            $siteName      = $device->site->name;
            $libreLocation = LibreLocation::getByName($siteName);

            if (!isset($libreLocation->id)) {
                Log::info('SyncDeviceLibreNMSJob: LibreNMS Location not found, creating', [
                    'site' => $siteName,
                ]);
                // Fetch full site object for coordinates
                $nbsite = \App\Models\Netbox\DCIM\Sites::where('name__ie', $siteName)->first();
                $params = [
                    'location' => $siteName,
                    'lat'      => $nbsite->latitude  ?? null,
                    'lng'      => $nbsite->longitude ?? null,
                ];
                try {
                    $libreLocation = LibreLocation::create($params);
                    if (isset($libreLocation->id)) {
                        Log::info('SyncDeviceLibreNMSJob: created LibreNMS Location', [
                            'location' => $siteName,
                            'id'       => $libreLocation->id,
                        ]);
                    } else {
                        Log::warning('SyncDeviceLibreNMSJob: failed to create LibreNMS Location', [
                            'site' => $siteName,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('SyncDeviceLibreNMSJob: exception creating Location', [
                        'site'  => $siteName,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                // Update coordinates if they have drifted
                $nbsite = \App\Models\Netbox\DCIM\Sites::where('name__ie', $siteName)->first();
                if (isset($nbsite->latitude) && isset($nbsite->longitude)) {
                    if ($nbsite->latitude != $libreLocation->lat || $nbsite->longitude != $libreLocation->lng) {
                        Log::info('SyncDeviceLibreNMSJob: updating Location coordinates', [
                            'location' => $siteName,
                        ]);
                        try {
                            $libreLocation->update([
                                'lat' => $nbsite->latitude,
                                'lng' => $nbsite->longitude,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('SyncDeviceLibreNMSJob: exception updating Location', [
                                'site'  => $siteName,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        }

        // ── 3. Ensure SITE_ DeviceGroup exists ────────────────────────────────
        if (isset($device->site->name) && $device->site->name) {
            $siteName   = $device->site->name;
            $groupName  = 'SITE_' . $siteName;
            try {
                $groups     = DeviceGroup::all();
                $groupMatch = $groups->where('name', $groupName)->first();
                if (!$groupMatch) {
                    Log::info('SyncDeviceLibreNMSJob: creating DeviceGroup', [
                        'group' => $groupName,
                    ]);
                    DeviceGroup::createSiteGroup($siteName);
                } else {
                    Log::info('SyncDeviceLibreNMSJob: DeviceGroup already exists', [
                        'group' => $groupName,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('SyncDeviceLibreNMSJob: exception managing DeviceGroup', [
                    'group' => $groupName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ── 4 & 5. Add or update the LibreNMS device entry ───────────────────
        $libreDevice = LibreDevice::find($hostname);

        if (!isset($libreDevice->device_id)) {
            // ── 4. Device does not exist in LibreNMS — add it ─────────────────
            Log::info('SyncDeviceLibreNMSJob: device not in LibreNMS, checking reachability', [
                'hostname' => $hostname,
            ]);

            $ping = $this->ping($hostname);
            if (!$ping) {
                Log::warning('SyncDeviceLibreNMSJob: device did not respond to ping, skipping add', [
                    'hostname' => $hostname,
                ]);
                return;
            }

            $body = [
                'hostname' => $hostname,
            ];
            if (isset($device->site->name)) {
                $body['location']              = $device->site->name;
                $body['override_sysLocation']  = 1;
            }
            if ($isOpengear) {
                $body['snmp_disable'] = true;
                $body['force_add']    = true;
            }

            try {
                $libreDevice = LibreDevice::create($body);
            } catch (\Exception $e) {
                Log::error('SyncDeviceLibreNMSJob: exception adding device', [
                    'hostname' => $hostname,
                    'error'    => $e->getMessage(),
                ]);
                return;
            }

            if (isset($libreDevice->device_id)) {
                Log::info('SyncDeviceLibreNMSJob: device added successfully', [
                    'hostname'  => $hostname,
                    'device_id' => $libreDevice->device_id,
                ]);
            } else {
                Log::error('SyncDeviceLibreNMSJob: device failed to add', [
                    'hostname' => $hostname,
                ]);
                return;
            }
        } else {
            Log::info('SyncDeviceLibreNMSJob: device already exists in LibreNMS', [
                'hostname'  => $hostname,
                'device_id' => $libreDevice->device_id,
            ]);
        }

        // ── 5a. Sync alerting / ignore flag ───────────────────────────────────
        $alertEnabled = isset($device->custom_fields->ALERT) && $device->custom_fields->ALERT === true;

        if ($alertEnabled && $libreDevice->ignore == 1) {
            Log::info('SyncDeviceLibreNMSJob: enabling alerting', ['hostname' => $hostname]);
            try {
                $libreDevice->enableAlerting();
            } catch (\Exception $e) {
                Log::error('SyncDeviceLibreNMSJob: exception enabling alerting', [
                    'hostname' => $hostname,
                    'error'    => $e->getMessage(),
                ]);
            }
        } elseif (!$alertEnabled && $libreDevice->ignore == 0) {
            Log::info('SyncDeviceLibreNMSJob: disabling alerting', ['hostname' => $hostname]);
            try {
                $libreDevice->disableAlerting();
            } catch (\Exception $e) {
                Log::error('SyncDeviceLibreNMSJob: exception disabling alerting', [
                    'hostname' => $hostname,
                    'error'    => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('SyncDeviceLibreNMSJob: alerting flag already correct', [
                'hostname' => $hostname,
                'ignore'   => $libreDevice->ignore,
            ]);
        }

        // ── 5b. Sync location ─────────────────────────────────────────────────
        if (isset($libreLocation->id)) {
            // Re-fetch fresh copy of the LibreNMS device to get current location_id
            $freshLibreDevice = LibreDevice::find($hostname);
            if (isset($freshLibreDevice->device_id) && $freshLibreDevice->location_id != $libreLocation->id) {
                Log::info('SyncDeviceLibreNMSJob: updating device location', [
                    'hostname'        => $hostname,
                    'old_location_id' => $freshLibreDevice->location_id,
                    'new_location_id' => $libreLocation->id,
                ]);
                try {
                    $freshLibreDevice->update([
                        'field' => ['location_id', 'override_sysLocation'],
                        'data'  => [$libreLocation->id, 1],
                    ]);
                } catch (\Exception $e) {
                    Log::error('SyncDeviceLibreNMSJob: exception updating device location', [
                        'hostname' => $hostname,
                        'error'    => $e->getMessage(),
                    ]);
                }
            } else {
                Log::info('SyncDeviceLibreNMSJob: device location already correct', [
                    'hostname'    => $hostname,
                    'location_id' => $libreLocation->id ?? null,
                ]);
            }
        }

        Log::info('SyncDeviceLibreNMSJob', [
            'state'            => 'complete',
            'netbox_device_id' => $this->netboxDeviceId,
            'name'             => $device->name,
            'hostname'         => $hostname,
        ]);
    }

    /**
     * Determine whether the given Netbox device is an Opengear device.
     * Opengear devices are added to LibreNMS with SNMP disabled (ICMP only).
     */
    protected function isOpengear(Devices $device): bool
    {
        if (!isset($device->device_type->manufacturer->name)) {
            return false;
        }
        return strtolower($device->device_type->manufacturer->name) === 'opengear';
    }

    /**
     * Ping a hostname and return the latency, or false if unreachable.
     */
    protected function ping(string $hostname, int $timeout = 5): mixed
    {
        $ping = new Ping($hostname);
        $ping->setTimeout($timeout);
        $latency = $ping->ping();
        return $latency ?: false;
    }
}
