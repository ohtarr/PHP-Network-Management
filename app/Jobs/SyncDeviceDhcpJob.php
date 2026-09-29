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
     * a MAC/IP via Devices::generateKeaReservation() (dhcp_id, Snipe-IT
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
     * Uses Devices::generateKeaReservation() to compute the desired Kea
     * reservation (subnetId / ipAddress / hwAddress or clientId /
     * usercontext->description), then reconciles against Kea:
     *   1. Look up an existing reservation by identifier (hwAddress, or
     *      clientId for option 61 reservations). If found and it doesn't
     *      fully match (ip + hwAddress + clientId + description), delete it.
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
        // generateKeaReservation() already returns null when there is no IP or
        // no matching Kea subnet. VirtualChassis::generateKeaReservation() can
        // still return one with neither hwAddress nor clientId set.
        $desired = $device->generateKeaReservation();

        if (!$desired || (empty($desired->hwAddress) && empty($desired->clientId))) {
            Log::info('SyncDeviceDhcpJob: no DHCP reservation to sync for device (missing MAC/client ID, IP, or DHCP scope)', [
                'name' => $device->name,
            ]);
            return;
        }

        $desiredDescription = $desired->usercontext->description ?? '';

        Log::info('SyncDeviceDhcpJob: desired reservation', ['reservation' => $desired]);

        $needsCreate = true;
        $handledIp   = null;

        // ── 3. Reconcile any existing reservation found by hwAddress/clientId ──
        $byIdentifier = $this->findExistingByIdentifier($desired);

        if ($byIdentifier) {
            if ($this->reservationMatches($byIdentifier, $desired)) {
                Log::info('SyncDeviceDhcpJob: reservation already correct (matched by identifier), leaving it alone', [
                    'hwaddress' => $byIdentifier->hwAddress ?? null,
                    'clientid'  => $byIdentifier->clientId ?? null,
                    'ipaddress' => $byIdentifier->ipAddress,
                ]);
                $needsCreate = false;
            } else {
                Log::info('SyncDeviceDhcpJob: deleting stale reservation matched by identifier', [
                    'hwaddress'    => $byIdentifier->hwAddress ?? null,
                    'clientid'     => $byIdentifier->clientId ?? null,
                    'old_ip'       => $byIdentifier->ipAddress,
                    'old_desc'     => $byIdentifier->usercontext->description ?? null,
                    'desired_ip'   => $desired->ipAddress,
                    'desired_desc' => $desiredDescription,
                ]);
                $this->deleteReservation($byIdentifier);
                $handledIp = $byIdentifier->ipAddress;
            }
        }

        // ── 4. Reconcile any existing reservation found by IP ───────────────────
        if ($needsCreate) {
            $byIp = ReservationV4::findByIp($desired->ipAddress);

            if ($byIp && $byIp->ipAddress !== $handledIp) {
                if ($this->reservationMatches($byIp, $desired)) {
                    Log::info('SyncDeviceDhcpJob: reservation already correct (matched by IP), leaving it alone', [
                        'hwaddress' => $byIp->hwAddress ?? null,
                        'clientid'  => $byIp->clientId ?? null,
                        'ipaddress' => $byIp->ipAddress,
                    ]);
                    $needsCreate = false;
                } else {
                    Log::info('SyncDeviceDhcpJob: deleting stale reservation matched by IP', [
                        'hwaddress'        => $byIp->hwAddress ?? null,
                        'clientid'         => $byIp->clientId ?? null,
                        'ipaddress'        => $byIp->ipAddress,
                        'old_desc'         => $byIp->usercontext->description ?? null,
                        'desired_hwaddr'   => $desired->hwAddress,
                        'desired_clientid' => $desired->clientId,
                        'desired_desc'     => $desiredDescription,
                    ]);
                    $this->deleteReservation($byIp);
                }
            }
        }

        // ── 5. Create the reservation if nothing already matched ───────────────
        if ($needsCreate) {
            Log::info('SyncDeviceDhcpJob: creating DHCP reservation', ['reservation' => $desired]);
            try {
                ReservationV4::createRaw($desired);
            } catch (\Exception $e) {
                Log::error('SyncDeviceDhcpJob: failed to create reservation', [
                    'reservation' => $desired,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Find an existing Kea reservation with the same identifier as the desired one.
     *
     * hwAddress reservations use the reservationv4/mac endpoint. The Kea API
     * has no clientId lookup, so option 61 reservations are found by searching
     * the reservations in the desired subnet. This means a stale reservation
     * with the same clientId in a different subnet is not found.
     */
    protected function findExistingByIdentifier(object $desired): ?ReservationV4
    {
        if (!empty($desired->hwAddress)) {
            return ReservationV4::findByMac($desired->hwAddress);
        }

        $subnet = SubnetV4::find((int) $desired->subnetId);
        if (!$subnet) {
            return null;
        }

        try {
            $reservations = $subnet->getReservations();
        } catch (\Exception $e) {
            Log::error('SyncDeviceDhcpJob: failed to fetch subnet reservations for client ID lookup', [
                'subnet_id' => $desired->subnetId,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }

        $desiredClientId = $this->normalizeHex($desired->clientId);

        return $reservations->first(function ($reservation) use ($desiredClientId) {
            return $this->normalizeHex($reservation->clientId ?? '') === $desiredClientId;
        });
    }

    /**
     * Whether an existing Kea reservation fully matches the desired
     * ip/hwAddress/clientId/description. hwAddress and clientId are normalized
     * before comparing because Kea returns colon-separated values while
     * Devices::generateKeaReservation() returns bare hex. A missing value
     * counts as empty, so a hwAddress reservation never matches a clientId
     * reservation.
     */
    protected function reservationMatches($existing, object $desired): bool
    {
        $existingDescription = $existing->usercontext->description ?? '';
        $desiredDescription  = $desired->usercontext->description ?? '';

        return $this->normalizeHex($existing->hwAddress ?? '') === $this->normalizeHex($desired->hwAddress ?? '')
            && $this->normalizeHex($existing->clientId ?? '') === $this->normalizeHex($desired->clientId ?? '')
            && ($existing->ipAddress ?? null) === $desired->ipAddress
            && $existingDescription === $desiredDescription;
    }

    /**
     * Normalize a hwAddress or clientId to bare lowercase hex so that
     * Kea's "aa:bb:cc:dd:ee:ff" and Netbox's "aabbccddeeff" compare equal.
     */
    protected function normalizeHex(?string $value): string
    {
        return strtolower(preg_replace('/[^0-9a-fA-F]/', '', $value ?? ''));
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
                'hwaddress' => $reservation->hwAddress ?? null,
                'clientid'  => $reservation->clientId ?? null,
                'ipaddress' => $reservation->ipAddress,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
