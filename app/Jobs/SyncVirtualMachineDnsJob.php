<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Netbox\VIRTUALIZATION\VirtualMachines;
use App\Models\Gizmo\DNS\A;

class SyncVirtualMachineDnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries   = 1;

    /**
     * The Netbox virtual machine ID to sync DNS records for.
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
     */
    public function handle(): void
    {
        Log::info("SyncVirtualMachineDnsJob starting for Netbox VM ID {$this->netboxVmId} (event: {$this->event}).");

        if ($this->event === 'deleted') {
            $this->handleDeleted();
        } else {
            $this->handleUpsert();
        }

        Log::info("SyncVirtualMachineDnsJob completed for Netbox VM ID {$this->netboxVmId} (event: {$this->event}).");
    }

    /**
     * Delete all DNS A records associated with this virtual machine.
     *
     * Since the VM has been deleted from Netbox we rely on the name supplied
     * from the webhook payload to derive the expected hostname, then delete
     * any matching records from DNS.
     */
    protected function handleDeleted(): void
    {
        if (!$this->vmName) {
            Log::warning('SyncVirtualMachineDnsJob: deleted event but no VM name provided, cannot clean up DNS', [
                'netbox_vm_id' => $this->netboxVmId,
            ]);
            return;
        }

        // Derive the base DNS name the same way VirtualMachines::generateDnsName() does.
        $dnsName = strtolower(str_replace(['.', '/'], '-', $this->vmName));

        Log::info('SyncVirtualMachineDnsJob: deleting DNS records for deleted VM', [
            'vm_name'  => $this->vmName,
            'dns_name' => $dnsName,
        ]);

        $existingRecords = A::all();

        foreach ($existingRecords as $record) {
            $hostname = strtolower($record->hostName);

            // Match the exact primary hostname OR any sub-record ending with ".<dnsname>".
            $isMatch = $hostname === $dnsName
                || str_ends_with($hostname, '.' . $dnsName);

            if ($isMatch) {
                Log::info('SyncVirtualMachineDnsJob: deleting DNS record for deleted VM', [
                    'hostname' => $record->hostName,
                    'ip'       => $record->recordData,
                ]);
                try {
                    $record->delete();
                } catch (\Exception $e) {
                    Log::error('SyncVirtualMachineDnsJob: failed to delete DNS record', [
                        'hostname' => $record->hostName,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Create or update DNS A records for this virtual machine (created / updated events).
     *
     * For each DNS name the VM should have:
     *   1. Delete any existing A record whose data (IP) no longer matches.
     *   2. Create the A record if it does not already exist.
     */
    protected function handleUpsert(): void
    {
        $cidrreg = '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})/';
        $ipreg   = '/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/';

        // ── 1. Fetch the Netbox virtual machine ───────────────────────────────
        $vm = VirtualMachines::find($this->netboxVmId);

        if (!isset($vm->id)) {
            Log::error('SyncVirtualMachineDnsJob: Netbox VM not found', [
                'netbox_vm_id' => $this->netboxVmId,
            ]);
            return;
        }

        if (!isset($vm->name) || !$vm->name) {
            Log::warning('SyncVirtualMachineDnsJob: VM has no name, skipping', [
                'netbox_vm_id' => $this->netboxVmId,
            ]);
            return;
        }

        Log::info('SyncVirtualMachineDnsJob: processing VM', [
            'name' => $vm->name,
            'id'   => $vm->id,
        ]);

        // ── 2. Build the desired DNS records for this VM ──────────────────────
        $desired = [];

        $dnsName = $vm->generateDnsName();

        if (isset($vm->primary_ip->address)) {
            if (preg_match($cidrreg, $vm->primary_ip->address, $hits)) {
                $desired[$dnsName] = $hits[1];
            }
        } elseif (isset($vm->custom_fields->ip) && $vm->custom_fields->ip) {
            if (preg_match($ipreg, $vm->custom_fields->ip, $hits)) {
                $desired[$dnsName] = $hits[1];
            }
        }

        if (empty($desired)) {
            Log::info('SyncVirtualMachineDnsJob: no DNS records to sync for VM (no IP address)', [
                'name' => $vm->name,
            ]);
            return;
        }

        Log::info('SyncVirtualMachineDnsJob: desired records', ['records' => $desired]);

        // ── 3. Fetch current A records and fix stale entries ──────────────────
        $existingRecords = A::all();

        foreach ($existingRecords as $record) {
            $hostname = strtolower($record->hostName);
            if (!array_key_exists($hostname, $desired)) {
                continue;
            }
            if ($record->recordData !== $desired[$hostname]) {
                Log::info('SyncVirtualMachineDnsJob: deleting stale record', [
                    'hostname'   => $record->hostName,
                    'old_ip'     => $record->recordData,
                    'desired_ip' => $desired[$hostname],
                ]);
                try {
                    $record->delete();
                } catch (\Exception $e) {
                    Log::error('SyncVirtualMachineDnsJob: failed to delete stale record', [
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
                Log::info('SyncVirtualMachineDnsJob: record already correct, skipping', [
                    'hostname' => $hostname,
                    'ip'       => $ip,
                ]);
                continue;
            }

            Log::info('SyncVirtualMachineDnsJob: creating A record', [
                'hostname' => $hostname,
                'ip'       => $ip,
            ]);
            try {
                A::create($hostname, $ip);
            } catch (\Exception $e) {
                Log::error('SyncVirtualMachineDnsJob: failed to create A record', [
                    'hostname' => $hostname,
                    'ip'       => $ip,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }
}
