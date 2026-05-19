<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Netbox\VIRTUALIZATION\VirtualMachines;
use App\Models\LibreNMS\Device as LibreDevice;
use App\Models\LibreNMS\Location as LibreLocation;
use JJG\Ping;

class SyncVirtualMachineLibreNMSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries   = 1;

    /**
     * The Netbox virtual machine ID to sync into LibreNMS.
     */
    public int $netboxVmId;

    /**
     * The webhook event type: 'created', 'updated', or 'deleted'.
     */
    public string $event;

    /**
     * The virtual machine name at the time of the webhook (used for deleted
     * events where the VM can no longer be fetched from Netbox).
     */
    public ?string $vmName;

    /**
     * Create a new job instance.
     *
     * @param  int         $netboxVmId  The Netbox virtualization virtual-machine ID
     * @param  string      $event       'created', 'updated', or 'deleted'
     * @param  string|null $vmName      VM name from webhook payload
     */
    public function __construct(int $netboxVmId, string $event = 'updated', ?string $vmName = null)
    {
        $this->netboxVmId = $netboxVmId;
        $this->event      = $event;
        $this->vmName     = $vmName;
    }

    /**
     * Execute the job.
     *
     * Mirrors the per-device logic from SyncDeviceLibreNMSJob but scoped to a
     * single Netbox virtual machine.  Steps performed:
     *
     *  1. Fetch the Netbox VM and determine its DNS hostname (generated name).
     *  2. Ensure the LibreNMS Location for the VM's site exists (create if not).
     *  3. If the VM is not yet in LibreNMS:
     *       - Ping the hostname first; skip if unreachable.
     *       - Add it, setting location.
     *  4. If the VM is already in LibreNMS:
     *       - Sync the ignore/alerting flag from Netbox custom_fields->ALERT.
     *       - Sync the location_id if it has drifted.
     */
    public function handle(): void
    {
        Log::info("SyncVirtualMachineLibreNMSJob starting for Netbox VM ID {$this->netboxVmId} (event: {$this->event}).");

        if ($this->event === 'deleted') {
            $this->handleDeleted();
            Log::info("SyncVirtualMachineLibreNMSJob completed (deleted) for Netbox VM ID {$this->netboxVmId}.");
            return;
        }

        // ── 1. Fetch the Netbox virtual machine ───────────────────────────────
        $vm = VirtualMachines::find($this->netboxVmId);

        if (!isset($vm->id)) {
            Log::error('SyncVirtualMachineLibreNMSJob: Netbox VM not found', [
                'netbox_vm_id' => $this->netboxVmId,
            ]);
            return;
        }

        if (!isset($vm->name) || !$vm->name) {
            Log::warning('SyncVirtualMachineLibreNMSJob: VM has no name, skipping', [
                'netbox_vm_id' => $this->netboxVmId,
            ]);
            return;
        }

        // Respect the POLLING custom field — if false, nothing to do.
        if (isset($vm->custom_fields->POLLING) && $vm->custom_fields->POLLING === false) {
            Log::info('SyncVirtualMachineLibreNMSJob: POLLING is disabled on VM, skipping', [
                'name' => $vm->name,
            ]);
            return;
        }

        // Determine the DNS hostname LibreNMS should use.
        $hostname = $vm->generateDnsName();

        Log::info('SyncVirtualMachineLibreNMSJob: processing VM', [
            'name'     => $vm->name,
            'id'       => $vm->id,
            'hostname' => $hostname,
        ]);

        // ── 2. Ensure LibreNMS Location exists for the VM's site ──────────────
        $libreLocation = null;
        if (isset($vm->site->name) && $vm->site->name) {
            $siteName      = $vm->site->name;
            $libreLocation = LibreLocation::getByName($siteName);

            if (!isset($libreLocation->id)) {
                Log::info('SyncVirtualMachineLibreNMSJob: LibreNMS Location not found, creating', [
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
                        Log::info('SyncVirtualMachineLibreNMSJob: created LibreNMS Location', [
                            'location' => $siteName,
                            'id'       => $libreLocation->id,
                        ]);
                    } else {
                        Log::warning('SyncVirtualMachineLibreNMSJob: failed to create LibreNMS Location', [
                            'site' => $siteName,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('SyncVirtualMachineLibreNMSJob: exception creating Location', [
                        'site'  => $siteName,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                // Update coordinates if they have drifted
                $nbsite = \App\Models\Netbox\DCIM\Sites::where('name__ie', $siteName)->first();
                if (isset($nbsite->latitude) && isset($nbsite->longitude)) {
                    if ($nbsite->latitude != $libreLocation->lat || $nbsite->longitude != $libreLocation->lng) {
                        Log::info('SyncVirtualMachineLibreNMSJob: updating Location coordinates', [
                            'location' => $siteName,
                        ]);
                        try {
                            $libreLocation->update([
                                'lat' => $nbsite->latitude,
                                'lng' => $nbsite->longitude,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('SyncVirtualMachineLibreNMSJob: exception updating Location', [
                                'site'  => $siteName,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        }

        // ── 3. Add or update the LibreNMS device entry ────────────────────────
        $libreDevice = null;
        try {
            $libreDevice = LibreDevice::find($hostname);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                Log::info('SyncVirtualMachineLibreNMSJob: VM not found in LibreNMS (404)', [
                    'hostname' => $hostname,
                ]);
            } else {
                Log::error('SyncVirtualMachineLibreNMSJob: unexpected HTTP error looking up VM', [
                    'hostname' => $hostname,
                    'status'   => $e->getResponse()->getStatusCode(),
                    'error'    => $e->getMessage(),
                ]);
                return;
            }
        } catch (\Exception $e) {
            Log::error('SyncVirtualMachineLibreNMSJob: exception looking up VM in LibreNMS', [
                'hostname' => $hostname,
                'error'    => $e->getMessage(),
            ]);
            return;
        }

        if (!isset($libreDevice->device_id)) {
            // ── 4. VM does not exist in LibreNMS — add it ─────────────────────
            Log::info('SyncVirtualMachineLibreNMSJob: VM not in LibreNMS, checking reachability', [
                'hostname' => $hostname,
            ]);

            $ping = $this->ping($hostname);
            if (!$ping) {
                Log::warning('SyncVirtualMachineLibreNMSJob: VM did not respond to ping, skipping add', [
                    'hostname' => $hostname,
                ]);
                return;
            }

            $body = [
                'hostname' => $hostname,
            ];
            if (isset($vm->site->name)) {
                $body['location']             = $vm->site->name;
                $body['override_sysLocation'] = 1;
            }

            try {
                $libreDevice = LibreDevice::create($body);
            } catch (\Exception $e) {
                Log::error('SyncVirtualMachineLibreNMSJob: exception adding VM', [
                    'hostname' => $hostname,
                    'error'    => $e->getMessage(),
                ]);
                return;
            }

            if (isset($libreDevice->device_id)) {
                Log::info('SyncVirtualMachineLibreNMSJob: VM added successfully', [
                    'hostname'  => $hostname,
                    'device_id' => $libreDevice->device_id,
                ]);
            } else {
                Log::error('SyncVirtualMachineLibreNMSJob: VM failed to add', [
                    'hostname' => $hostname,
                ]);
                return;
            }
        } else {
            Log::info('SyncVirtualMachineLibreNMSJob: VM already exists in LibreNMS', [
                'hostname'  => $hostname,
                'device_id' => $libreDevice->device_id,
            ]);
        }

        // ── 5a. Sync alerting / ignore flag ───────────────────────────────────
        $alertEnabled  = isset($vm->custom_fields->ALERT) && $vm->custom_fields->ALERT === true;
        $currentIgnore = $libreDevice->ignore ?? null;

        if ($alertEnabled && $currentIgnore == 1) {
            Log::info('SyncVirtualMachineLibreNMSJob: enabling alerting', ['hostname' => $hostname]);
            try {
                $libreDevice->enableAlerting();
            } catch (\Exception $e) {
                Log::error('SyncVirtualMachineLibreNMSJob: exception enabling alerting', [
                    'hostname' => $hostname,
                    'error'    => $e->getMessage(),
                ]);
            }
        } elseif (!$alertEnabled && $currentIgnore == 0) {
            Log::info('SyncVirtualMachineLibreNMSJob: disabling alerting', ['hostname' => $hostname]);
            try {
                $libreDevice->disableAlerting();
            } catch (\Exception $e) {
                Log::error('SyncVirtualMachineLibreNMSJob: exception disabling alerting', [
                    'hostname' => $hostname,
                    'error'    => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('SyncVirtualMachineLibreNMSJob: alerting flag already correct', [
                'hostname' => $hostname,
                'ignore'   => $currentIgnore,
            ]);
        }

        // ── 5b. Sync location ─────────────────────────────────────────────────
        if (isset($libreLocation->id)) {
            // Re-fetch fresh copy of the LibreNMS device to get current location_id
            $freshLibreDevice = null;
            try {
                $freshLibreDevice = LibreDevice::find($hostname);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                Log::warning('SyncVirtualMachineLibreNMSJob: could not re-fetch VM for location sync', [
                    'hostname' => $hostname,
                    'status'   => $e->getResponse()->getStatusCode(),
                    'error'    => $e->getMessage(),
                ]);
            } catch (\Exception $e) {
                Log::warning('SyncVirtualMachineLibreNMSJob: exception re-fetching VM for location sync', [
                    'hostname' => $hostname,
                    'error'    => $e->getMessage(),
                ]);
            }
            if (isset($freshLibreDevice->device_id) && $freshLibreDevice->location_id != $libreLocation->id) {
                Log::info('SyncVirtualMachineLibreNMSJob: updating VM location', [
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
                    Log::error('SyncVirtualMachineLibreNMSJob: exception updating VM location', [
                        'hostname' => $hostname,
                        'error'    => $e->getMessage(),
                    ]);
                }
            } else {
                Log::info('SyncVirtualMachineLibreNMSJob: VM location already correct', [
                    'hostname'    => $hostname,
                    'location_id' => $libreLocation->id ?? null,
                ]);
            }
        }

        Log::info("SyncVirtualMachineLibreNMSJob completed for Netbox VM ID {$this->netboxVmId} ({$vm->name}).");
    }

    /**
     * Delete the LibreNMS device entry for a Netbox VM that has been deleted.
     *
     * Derives the expected LibreNMS hostname from the VM name supplied in
     * the webhook payload (since the VM no longer exists in Netbox).
     */
    protected function handleDeleted(): void
    {
        if (!$this->vmName) {
            Log::warning('SyncVirtualMachineLibreNMSJob: deleted event but no VM name provided, cannot clean up LibreNMS', [
                'netbox_vm_id' => $this->netboxVmId,
            ]);
            return;
        }

        // Derive hostname the same way generateDnsName() does.
        $hostname = strtolower(str_replace(['.', '/'], '-', $this->vmName));

        try {
            $libreDevice = LibreDevice::find($hostname);
        } catch (\Exception $e) {
            Log::warning('SyncVirtualMachineLibreNMSJob: exception looking up hostname in LibreNMS, skipping', [
                'hostname' => $hostname,
                'error'    => $e->getMessage(),
            ]);
            return;
        }

        if (!isset($libreDevice->device_id)) {
            Log::info('SyncVirtualMachineLibreNMSJob: VM not found in LibreNMS, nothing to delete', [
                'hostname' => $hostname,
            ]);
            return;
        }

        Log::info('SyncVirtualMachineLibreNMSJob: deleting VM from LibreNMS', [
            'hostname'  => $hostname,
            'device_id' => $libreDevice->device_id,
        ]);

        try {
            $result = $libreDevice->delete();
            if ($result) {
                Log::info('SyncVirtualMachineLibreNMSJob: VM deleted from LibreNMS successfully', [
                    'hostname'  => $hostname,
                    'device_id' => $libreDevice->device_id,
                ]);
            } else {
                Log::error('SyncVirtualMachineLibreNMSJob: VM delete returned failure', [
                    'hostname'  => $hostname,
                    'device_id' => $libreDevice->device_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SyncVirtualMachineLibreNMSJob: exception deleting VM from LibreNMS', [
                'hostname' => $hostname,
                'error'    => $e->getMessage(),
            ]);
        }
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
