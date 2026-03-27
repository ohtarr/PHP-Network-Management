<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Netbox\DCIM\Interfaces;
use App\Models\Netbox\IPAM\IpAddresses;
use App\Models\Gizmo\DNS\A;
use App\Models\Log\Log as DbLog;

class SyncDeviceDnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries   = 1;

    /**
     * The Netbox device ID to sync DNS records for.
     */
    public int $netboxDeviceId;

    /**
     * The webhook event type: 'created', 'updated', or 'deleted'.
     */
    public string $event;

    /**
     * The device name at the time of the webhook (used for deleted events
     * where the device can no longer be fetched from Netbox).
     */
    public ?string $deviceName;

    /**
     * Create a new job instance.
     *
     * @param  int     $netboxDeviceId  The Netbox DCIM device ID
     * @param  string  $event           'created', 'updated', or 'deleted'
     * @param  string|null  $deviceName Device name from webhook payload
     */
    public function __construct(int $netboxDeviceId, string $event = 'updated', ?string $deviceName = null)
    {
        $this->netboxDeviceId = $netboxDeviceId;
        $this->event          = $event;
        $this->deviceName     = $deviceName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DbLog::log(
            "SyncDeviceDnsJob starting for Netbox device ID {$this->netboxDeviceId} (event: {$this->event})."
        );

        if ($this->event === 'deleted') {
            $this->handleDeleted();
        } else {
            $this->handleUpsert();
        }

        DbLog::log(
            "SyncDeviceDnsJob completed for Netbox device ID {$this->netboxDeviceId} (event: {$this->event})."
        );
    }

    /**
     * Delete all DNS A records associated with this device.
     *
     * Since the device has been deleted from Netbox we rely on the name
     * supplied from the webhook payload to derive the expected hostnames,
     * then delete any matching records from DNS.
     */
    protected function handleDeleted(): void
    {
        if (!$this->deviceName) {
            Log::warning('SyncDeviceDnsJob: deleted event but no device name provided, cannot clean up DNS', [
                'netbox_device_id' => $this->netboxDeviceId,
            ]);
            return;
        }

        // Derive the base DNS name the same way Devices::generateDnsName() does.
        $dnsName = strtolower(str_replace(['.', '/'], '-', $this->deviceName));

        // Hostnames to remove: primary (<dnsname>), oob standalone (<dnsname>-oob),
        // and VC-master oob (oob.<dnsname>).
        $hostnamesToDelete = [
            $dnsName,
            $dnsName . '-oob',
            'oob.' . $dnsName,
        ];

        Log::info('SyncDeviceDnsJob: deleting DNS records for deleted device', [
            'device_name' => $this->deviceName,
            'hostnames'   => $hostnamesToDelete,
        ]);

        $existingRecords = A::all();

        foreach ($existingRecords as $record) {
            $hostname = strtolower($record->hostName);

            // Match exact primary/oob hostnames OR any per-interface record
            // whose hostname ends with ".<dnsname>" (e.g. gi0-0.site001rwa01).
            $isMatch = in_array($hostname, $hostnamesToDelete)
                || str_ends_with($hostname, '.' . $dnsName);

            if ($isMatch) {
                Log::info('SyncDeviceDnsJob: deleting DNS record for deleted device', [
                    'hostname' => $record->hostName,
                    'ip'       => $record->recordData,
                ]);
                try {
                    $record->delete();
                } catch (\Exception $e) {
                    Log::error('SyncDeviceDnsJob: failed to delete DNS record', [
                        'hostname' => $record->hostName,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Create or update DNS A records for this device (created / updated events).
     *
     * Mirrors the logic in syncDns::generateAllRecords() but scoped to a
     * single Netbox device.  For each DNS name the device should have:
     *   1. Delete any existing A record whose data (IP) no longer matches.
     *   2. Create the A record if it does not already exist.
     *
     * Also handles:
     *   - primary_ip  → <dnsname>
     *   - oob_ip      → <dnsname>-oob  (standalone) / oob.<dnsname> (VC master)
     *   - custom_fields->ip fallback when no primary_ip is set
     *   - Per-interface A records for every assigned IP address
     *   - Virtual-chassis: uses the VC master's IP / name for the chassis DNS entry
     */
    protected function handleUpsert(): void
    {
        $cidrreg = '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/';
        $ipreg   = '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/';

        // ── 1. Fetch the Netbox device ────────────────────────────────────────
        $device = Devices::find($this->netboxDeviceId);

        if (!isset($device->id)) {
            Log::error('SyncDeviceDnsJob: Netbox device not found', [
                'netbox_device_id' => $this->netboxDeviceId,
            ]);
            return;
        }

        if (!isset($device->name) || !$device->name) {
            Log::warning('SyncDeviceDnsJob: device has no name, skipping', [
                'netbox_device_id' => $this->netboxDeviceId,
            ]);
            return;
        }

        Log::info('SyncDeviceDnsJob: processing device', [
            'name' => $device->name,
            'id'   => $device->id,
        ]);

        // ── 2. Build the desired DNS records for this device ──────────────────
        $desired = [];

        $isVcMember = isset($device->virtual_chassis->id);
        $isVcMaster = $isVcMember
            && isset($device->virtual_chassis->master->id)
            && $device->virtual_chassis->master->id === $device->id;

        if ($isVcMember && !$isVcMaster) {
            Log::info('SyncDeviceDnsJob: device is a non-master VC member, skipping chassis DNS', [
                'name' => $device->name,
            ]);
        } else {
            $dnsName = $device->generateDnsName();

            if (isset($device->primary_ip->address)) {
                if (preg_match($cidrreg, $device->primary_ip->address, $hits)) {
                    $desired[$dnsName] = $hits[1];
                }
            } elseif (isset($device->custom_fields->ip) && $device->custom_fields->ip) {
                if (preg_match($ipreg, $device->custom_fields->ip, $hits)) {
                    $desired[$dnsName] = $hits[1];
                }
            }

            if (isset($device->oob_ip->address)) {
                if (preg_match($cidrreg, $device->oob_ip->address, $hits)) {
                    $oobDnsName = $isVcMaster
                        ? 'oob.' . $dnsName
                        : $dnsName . '-oob';
                    $desired[$oobDnsName] = $hits[1];
                }
            }
        }

        // Per-interface IP addresses
        $interfaceIps = IpAddresses::where('assigned_to_interface', 'true')
            ->where('limit', '1000')
            ->get();

        foreach ($interfaceIps as $ip) {
            if ($ip->assigned_object_type !== 'dcim.interface') {
                continue;
            }
            if (!isset($ip->assigned_object->name)) {
                continue;
            }
            if (!isset($ip->assigned_object->device->name)) {
                continue;
            }
            if (strtolower($ip->assigned_object->device->name) !== strtolower($device->name)) {
                continue;
            }
            $intDnsName = Interfaces::generateDnsNameStatic(
                $ip->assigned_object->name,
                $ip->assigned_object->device->name
            );
            if ($intDnsName) {
                $desired[$intDnsName] = $ip->cidr()['ip'];
            }
        }

        if (empty($desired)) {
            Log::info('SyncDeviceDnsJob: no DNS records to sync for device', [
                'name' => $device->name,
            ]);
            return;
        }

        Log::info('SyncDeviceDnsJob: desired records', ['records' => $desired]);

        // ── 3. Fetch current A records and fix stale entries ──────────────────
        $existingRecords = A::all();

        foreach ($existingRecords as $record) {
            $hostname = strtolower($record->hostName);
            if (!array_key_exists($hostname, $desired)) {
                continue;
            }
            if ($record->recordData !== $desired[$hostname]) {
                Log::info('SyncDeviceDnsJob: deleting stale record', [
                    'hostname'   => $record->hostName,
                    'old_ip'     => $record->recordData,
                    'desired_ip' => $desired[$hostname],
                ]);
                try {
                    $record->delete();
                } catch (\Exception $e) {
                    Log::error('SyncDeviceDnsJob: failed to delete record', [
                        'hostname' => $record->hostName,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }

        // ── 4. Create missing records ─────────────────────────────────────────
        $freshRecords = A::all();
        $existingMap  = [];
        foreach ($freshRecords as $record) {
            $existingMap[strtolower($record->hostName)] = $record->recordData;
        }

        foreach ($desired as $hostname => $ip) {
            if (isset($existingMap[$hostname]) && $existingMap[$hostname] === $ip) {
                Log::info('SyncDeviceDnsJob: record already correct, skipping', [
                    'hostname' => $hostname,
                    'ip'       => $ip,
                ]);
                continue;
            }

            Log::info('SyncDeviceDnsJob: creating A record', [
                'hostname' => $hostname,
                'ip'       => $ip,
            ]);
            try {
                A::create($hostname, $ip);
            } catch (\Exception $e) {
                Log::error('SyncDeviceDnsJob: failed to create A record', [
                    'hostname' => $hostname,
                    'ip'       => $ip,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }
}
