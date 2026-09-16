<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Netbox\DCIM\Devices;
use App\Models\Dhcp\ReservationV4;
use App\Models\Dhcp\SubnetV4;

class SyncDeviceDhcpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries   = 1;

    /**
     * The Netbox device ID to sync the DHCP reservation for.
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
        Log::info("SyncDeviceDhcpJob starting for Netbox device ID {$this->netboxDeviceId} (event: {$this->event}).");

        if ($this->event === 'deleted') {
            $this->handleDeleted();
        } else {
            $this->handleUpsert();
        }

        Log::info("SyncDeviceDhcpJob completed for Netbox device ID {$this->netboxDeviceId} (event: {$this->event}).");
    }

    /**
     * A device that's been deleted from Netbox can no longer be resolved to
     * a MAC/IP via Devices::generateDhcpReservation() (dhcp_id, Snipe-IT
     * asset, and assigned IP all come from the live Netbox record), so
     * there's no reliable key to look up or remove the reservation by.
     * Log it so it can be cleaned up manually if needed.
     */
    protected function handleDeleted(): void
    {
        Log::warning('SyncDeviceDhcpJob: deleted event received, DHCP reservation cleanup is not supported (no MAC/IP available for a deleted device)', [
            'netbox_device_id' => $this->netboxDeviceId,
            'device_name'      => $this->deviceName,
        ]);
    }

    /**
     * Create or update the DHCP reservation for this device (created / updated events).
     *
     * Uses Devices::generateDhcpReservation() to compute the desired
     * ipaddress / hwaddress / description, then reconciles against Kea:
     *   1. Look up an existing reservation by MAC. If found and it doesn't
     *      fully match (ip + mac + description), delete it.
     *   2. Look up an existing reservation by IP (skipping one already
     *      handled above). If found and it doesn't fully match, delete it.
     *   3. If nothing already matched, create the desired reservation.
     */
    protected function handleUpsert(): void
    {
        // ── 1. Fetch the Netbox device ────────────────────────────────────────
        $device = Devices::find($this->netboxDeviceId);

        if (!isset($device->id)) {
            Log::error('SyncDeviceDhcpJob: Netbox device not found', [
                'netbox_device_id' => $this->netboxDeviceId,
            ]);
            return;
        }

        if (!isset($device->name) || !$device->name) {
            Log::warning('SyncDeviceDhcpJob: device has no name, skipping', [
                'netbox_device_id' => $this->netboxDeviceId,
            ]);
            return;
        }

        Log::info('SyncDeviceDhcpJob: processing device', [
            'name' => $device->name,
            'id'   => $device->id,
        ]);

        // ── 2. Build the desired reservation for this device ──────────────────
        $desired = $device->generateDhcpReservation();

        if (!$desired) {
            Log::info('SyncDeviceDhcpJob: no DHCP reservation to sync for device (missing MAC/dhcp_id or IP)', [
                'name' => $device->name,
            ]);
            return;
        }

        Log::info('SyncDeviceDhcpJob: desired reservation', ['reservation' => $desired]);

        $needsCreate = true;
        $handledIp   = null;

        // ── 3. Reconcile any existing reservation found by MAC ─────────────────
        $byMac = ReservationV4::findByMac($desired['hwaddress']);

        if ($byMac) {
            if ($this->reservationMatches($byMac, $desired)) {
                Log::info('SyncDeviceDhcpJob: reservation already correct (matched by MAC), leaving it alone', [
                    'hwaddress' => $byMac->hwAddress,
                    'ipaddress' => $byMac->ipAddress,
                ]);
                $needsCreate = false;
            } else {
                Log::info('SyncDeviceDhcpJob: deleting stale reservation matched by MAC', [
                    'hwaddress'    => $byMac->hwAddress,
                    'old_ip'       => $byMac->ipAddress,
                    'old_desc'     => $byMac->usercontext->description ?? null,
                    'desired_ip'   => $desired['ipaddress'],
                    'desired_desc' => $desired['description'],
                ]);
                $this->deleteReservation($byMac);
                $handledIp = $byMac->ipAddress;
            }
        }

        // ── 4. Reconcile any existing reservation found by IP ───────────────────
        if ($needsCreate) {
            $byIp = ReservationV4::findByIp($desired['ipaddress']);

            if ($byIp && $byIp->ipAddress !== $handledIp) {
                if ($this->reservationMatches($byIp, $desired)) {
                    Log::info('SyncDeviceDhcpJob: reservation already correct (matched by IP), leaving it alone', [
                        'hwaddress' => $byIp->hwAddress,
                        'ipaddress' => $byIp->ipAddress,
                    ]);
                    $needsCreate = false;
                } else {
                    Log::info('SyncDeviceDhcpJob: deleting stale reservation matched by IP', [
                        'hwaddress'    => $byIp->hwAddress,
                        'ipaddress'    => $byIp->ipAddress,
                        'old_desc'     => $byIp->usercontext->description ?? null,
                        'desired_mac'  => $desired['hwaddress'],
                        'desired_desc' => $desired['description'],
                    ]);
                    $this->deleteReservation($byIp);
                }
            }
        }

        // ── 5. Create the reservation if nothing already matched ───────────────
        if ($needsCreate) {
            $scope = SubnetV4::findByIp($desired['ipaddress']);
            if (!$scope) {
                Log::warning('SyncDeviceDhcpJob: no DHCP scope found for IP, skipping reservation creation', [
                    'reservation' => $desired,
                ]);
                return;
            }

            Log::info('SyncDeviceDhcpJob: creating DHCP reservation', ['reservation' => $desired]);
            try {
                ReservationV4::create($desired['ipaddress'], $desired['hwaddress'], $desired['description']);
            } catch (\Exception $e) {
                Log::error('SyncDeviceDhcpJob: failed to create reservation', [
                    'reservation' => $desired,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Whether an existing Kea reservation fully matches the desired
     * ip/mac/description. MAC addresses are normalized before comparing
     * since Kea returns colon-separated hwAddress values while
     * Devices::generateDhcpReservation() returns bare hex.
     */
    protected function reservationMatches($existing, array $desired): bool
    {
        $existingMac = $this->normalizeMacAddress($existing->hwAddress ?? '');
        $desiredMac  = $this->normalizeMacAddress($desired['hwaddress']);
        $existingDescription = $existing->usercontext->description ?? '';

        return $existingMac === $desiredMac
            && ($existing->ipAddress ?? null) === $desired['ipaddress']
            && $existingDescription === $desired['description'];
    }

    /**
     * Normalize a MAC address to lowercase colon-separated hex so that
     * Kea's "aa:bb:cc:dd:ee:ff" and Netbox's bare "aabbccddeeff" compare equal.
     */
    protected function normalizeMacAddress(string $mac): string
    {
        $hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', $mac));
        return implode(':', str_split($hex, 2));
    }

    /**
     * Delete a Kea reservation, logging on failure without throwing.
     */
    protected function deleteReservation(ReservationV4 $reservation): void
    {
        try {
            $reservation->delete();
        } catch (\Exception $e) {
            Log::error('SyncDeviceDhcpJob: failed to delete reservation', [
                'hwaddress' => $reservation->hwAddress,
                'ipaddress' => $reservation->ipAddress,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
