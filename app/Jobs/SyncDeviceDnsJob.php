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
use App\Models\Gizmo\DNS\Cname;

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
    public function handle(): void
    {
        $cidrreg = '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/';
        $ipreg   = '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/';

        Log::info('SyncDeviceDnsJob', [
            'state'            => 'starting',
            'netbox_device_id' => $this->netboxDeviceId,
        ]);

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
        // $desired is an associative array: [ 'hostname' => 'ip', ... ]
        $desired = [];

        $isVcMember = isset($device->virtual_chassis->id);
        $isVcMaster = $isVcMember
            && isset($device->virtual_chassis->master->id)
            && $device->virtual_chassis->master->id === $device->id;

        if ($isVcMember && !$isVcMaster) {
            // Non-master VC members do not get their own DNS entry;
            // the chassis entry is owned by the master.
            Log::info('SyncDeviceDnsJob: device is a non-master VC member, skipping chassis DNS', [
                'name' => $device->name,
            ]);
        } else {
            // Standalone device OR VC master ─────────────────────────────────

            $dnsName = $device->generateDnsName(); // handles VC name substitution

            // primary_ip → <dnsname>
            if (isset($device->primary_ip->address)) {
                if (preg_match($cidrreg, $device->primary_ip->address, $hits)) {
                    $desired[$dnsName] = $hits[1];
                }
            } elseif (isset($device->custom_fields->ip) && $device->custom_fields->ip) {
                // Fallback: custom_fields->ip (no CIDR notation)
                if (preg_match($ipreg, $device->custom_fields->ip, $hits)) {
                    $desired[$dnsName] = $hits[1];
                }
            }

            // oob_ip → <dnsname>-oob  (standalone) or oob.<dnsname> (VC master)
            if (isset($device->oob_ip->address)) {
                if (preg_match($cidrreg, $device->oob_ip->address, $hits)) {
                    $oobDnsName = $isVcMaster
                        ? 'oob.' . $dnsName
                        : $dnsName . '-oob';
                    $desired[$oobDnsName] = $hits[1];
                }
            }
        }

        // Per-interface IP addresses ──────────────────────────────────────────
        // Mirrors the IpAddresses loop in syncDns::generateAllRecords()
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
            // Only process IPs that belong to THIS device
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

        // ── 3. Fetch current A records from DNS ───────────────────────────────
        $existingRecords = A::all();

        // ── 4. Fix / delete stale records ────────────────────────────────────
        // If a record exists for one of our desired hostnames but points to the
        // wrong IP, delete it so it can be re-created in step 5.
        foreach ($existingRecords as $record) {
            $hostname = strtolower($record->hostName);
            if (!array_key_exists($hostname, $desired)) {
                continue; // belongs to a different device, leave it alone
            }
            if ($record->recordData !== $desired[$hostname]) {
                Log::info('SyncDeviceDnsJob: deleting stale record', [
                    'hostname'    => $record->hostName,
                    'old_ip'      => $record->recordData,
                    'desired_ip'  => $desired[$hostname],
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

        // ── 5. Re-fetch and add missing records ───────────────────────────────
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

        Log::info('SyncDeviceDnsJob', [
            'state'            => 'complete',
            'netbox_device_id' => $this->netboxDeviceId,
            'name'             => $device->name,
        ]);
    }
}
